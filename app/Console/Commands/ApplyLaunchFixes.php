<?php

namespace App\Console\Commands;

use App\Support\PublicCache;
use App\Support\SystemCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One-off, idempotent clean-up of content already stored in a database
 * (the code changes ship as files; stored section data does not).
 *
 *  - removes email addresses from section content and blanks the public email setting
 *  - drops the placeholder "[Map location]" text from the centres
 *  - rewrites legacy "contact.html?type=visit#enquiry" / "?type=visit#enquiry" button links
 *    (the admin editor refuses to save a page that still contains them)
 *  - clears the cached website HTML
 *
 * Safe to run more than once: rows are only written when something changes.
 */
class ApplyLaunchFixes extends Command
{
    protected $signature = 'cms:launch-fixes {--dry-run : Report what would change without writing anything}';

    protected $description = 'Clean stored section data (emails, map placeholder, legacy links) and clear the website cache';

    private const LEGACY_LINKS = ['contact.html?type=visit#enquiry', '?type=visit#enquiry'];

    private const VISIT_LINK = '/contact?type=visit#enquiry';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = ['emails' => 0, 'map' => 0, 'links' => 0, 'cards' => 0];
        $touched = [];

        foreach (DB::table('page_sections')->get() as $row) {
            $data = json_decode($row->data, true);
            if (! is_array($data)) {
                $this->warn("Section {$row->id}: data is not valid JSON, skipped.");

                continue;
            }
            $before = $data;

            $this->walk($data, $changed);

            // Contact details: the "Email Us" card goes entirely.
            if ($row->type === 'inner-contact-details' && isset($data['items']) && is_array($data['items'])) {
                $kept = array_values(array_filter($data['items'], function ($it) use (&$changed) {
                    $gone = is_array($it) && stripos((string) ($it['heading'] ?? ''), 'email') !== false
                        && preg_match('/mailto:|@/', json_encode($it));
                    if ($gone) {
                        $changed['cards']++;
                    }

                    return ! $gone;
                }));
                $data['items'] = $kept;
            }

            if ($data !== $before) {
                $touched[] = $row->id;
                if (! $dry) {
                    DB::table('page_sections')->where('id', $row->id)->update([
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $emailSetting = DB::table('settings')->where('key', 'contact_email')->first();
        $settingNeedsBlank = $emailSetting && json_decode($emailSetting->value, true) !== '' && json_decode($emailSetting->value, true) !== null;
        if ($settingNeedsBlank && ! $dry) {
            DB::table('settings')->where('key', 'contact_email')->update(['value' => '""', 'updated_at' => now()]);
        }

        $this->table(['What', 'Count'], [
            ['Email addresses / email links removed from text', $changed['emails']],
            ['"Email Us" cards removed', $changed['cards']],
            ['"[Map location]" placeholders removed', $changed['map']],
            ['Legacy visit links rewritten', $changed['links']],
            ['Public email setting blanked', $settingNeedsBlank ? 1 : 0],
            ['Sections '.($dry ? 'that would be ' : '').'updated', count($touched)],
        ]);

        if ($dry) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        PublicCache::flush();
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            $this->warn('Could not flush the whole cache store: '.$e->getMessage());
        }
        $this->info('Website cache cleared.');

        $this->newLine();
        $this->line('System check:');
        foreach (SystemCheck::run() as $c) {
            $this->line(sprintf('  [%s] %s — %s', $c['ok'] ? ' OK ' : 'FIX!', $c['label'], $c['detail']));
        }

        return self::SUCCESS;
    }

    /** Recursively clean strings and link fields in a decoded section payload. */
    private function walk(array &$node, array &$changed): void
    {
        foreach ($node as $key => &$value) {
            if (is_array($value)) {
                $this->walk($value, $changed);

                continue;
            }
            if (! is_string($value)) {
                continue;
            }
            $old = $value;

            if ($key === 'url' && in_array($value, self::LEGACY_LINKS, true)) {
                $value = self::VISIT_LINK;
                $changed['links']++;

                continue;
            }
            if ($key === 'text_3' && trim($value) === '[Map location]') {
                $value = '';
                $changed['map']++;

                continue;
            }

            // "..., call +91 .. or email x@y, and our team" -> "... or call +91 .., and our team"
            $value = preg_replace('/,\s*call\s+(\+[\d ]+)\s+or\s+email\s+[^\s,<]+@[^\s,<]+,/u', ' or call $1,', $value);
            // Careers: ", or email your CV and a recent photograph to <a mailto>..</a>."
            $value = preg_replace('/,\s*or\s+email\s+your\s+CV\s+and\s+a\s+recent\s+photograph\s+to\s*<a\s+href="mailto:[^"]*">[^<]*<\/a>\s*\./u', '.', $value);
            // Trailing " · <a mailto>..</a>" contact tails
            $value = preg_replace('/\s*[^\s<>)\w]{1,3}\s*<a\s+href="mailto:[^"]*">[^<]*<\/a>/u', '', $value);

            if ($value !== $old) {
                $changed['emails']++;
            }
        }
    }
}
