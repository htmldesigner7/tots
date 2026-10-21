<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function jpeg(int $w = 1600, int $h = 1000, string $name = 'Kids Painting.jpg'): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w / 2, $h, imagecolorallocate($im, 103, 56, 147));
        ob_start();
        imagejpeg($im, null, 95);
        $data = ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $data);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function upload(UploadedFile $file)
    {
        return $this->post('/admin/media', ['files' => [$file]], ['Accept' => 'application/json']);
    }

    public function test_upload_optimises_and_keeps_original(): void
    {
        $this->admin();
        $this->upload($this->jpeg())->assertOk()->assertJsonCount(1, 'items');
        $m = Media::with('variants')->firstOrFail();
        $this->assertSame('image/jpeg', $m->mime);
        $this->assertSame([1600, 1000], [$m->width, $m->height]);
        $this->assertStringStartsWith('kids-painting-', $m->filename);
        Storage::disk('public')->assertExists($m->path);
        Storage::disk('local')->assertExists($m->original_path);   // recoverable, not public
        Storage::disk('public')->assertMissing($m->original_path);
        $widths = $m->variants->where('format', 'webp')->where('name', '!=', 'thumb')->pluck('width')->sort()->values()->all();
        $this->assertSame([320, 480, 768, 1024, 1440, 1600], $widths);   // never upscaled
        $this->assertNotNull($m->variants->firstWhere('name', 'thumb'));
        $this->assertArrayHasKey('saved_percent', $m->optimization);
    }

    public function test_small_images_are_not_upscaled(): void
    {
        $this->admin();
        $this->upload($this->jpeg(300, 200))->assertOk();
        $m = Media::with('variants')->first();
        $this->assertSame(300, $m->width);
        $this->assertSame(300, $m->variants->where('name', '!=', 'thumb')->max('width'));
    }

    public function test_fake_and_unsafe_files_are_rejected(): void
    {
        $this->admin();
        $php = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($php, '<?php echo "pwned"; ?>');
        $this->upload(new UploadedFile($php, 'photo.jpg', 'image/jpeg', null, true))->assertStatus(422);
        $this->upload(UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))->assertStatus(422);
        $this->upload(UploadedFile::fake()->create('shell.php', 1, 'text/x-php'))->assertStatus(422);
        $this->assertSame(0, Media::count());
    }

    public function test_svg_is_sanitised(): void
    {
        $this->admin();
        $svg = tempnam(sys_get_temp_dir(), 's');
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)"><rect width="10" height="10" fill="red"/></a><foreignObject><div>x</div></foreignObject></svg>');
        $this->upload(new UploadedFile($svg, 'logo.svg', 'image/svg+xml', null, true))->assertOk();
        $m = Media::first();
        $stored = Storage::disk('public')->get($m->path);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('javascript', $stored);
        $this->assertStringNotContainsString('foreignObject', $stored);
        $this->assertStringContainsString('viewBox="0 0 10 10"', $stored);
    }

    public function test_crop_rotate_is_non_destructive_and_resettable(): void
    {
        $this->admin();
        $this->upload($this->jpeg(1600, 1000));
        $m = Media::first();
        $original = Storage::disk('local')->get($m->original_path);
        $this->post("/admin/media/{$m->id}/edit", ['x' => 0, 'y' => 0, 'width' => 1000, 'height' => 1000, 'rotate' => 90])->assertRedirect();
        $m->refresh();
        $this->assertSame([1000, 1000], [$m->width, $m->height]);
        $this->assertSame(90, $m->edits['rotate']);
        $this->assertSame($original, Storage::disk('local')->get($m->original_path));
        $this->post("/admin/media/{$m->id}/reset")->assertRedirect();
        $this->assertSame([1600, 1000], [$m->fresh()->width, $m->fresh()->height]);
        // save as copy
        $this->post("/admin/media/{$m->id}/edit", ['x' => 100, 'y' => 100, 'width' => 400, 'height' => 300, 'as_copy' => 1])->assertRedirect();
        $this->assertSame(2, Media::count());
        $this->assertSame([1600, 1000], [$m->fresh()->width, $m->fresh()->height]);
    }

    public function test_used_media_cannot_be_deleted_and_replacement_keeps_placements(): void
    {
        $this->admin();
        $this->upload($this->jpeg());
        $m = Media::first();
        $page = Page::where('slug', 'about')->first();
        $sections = $page->sections->map(fn ($s) => ['id' => $s->id, 'type' => $s->type, 'anchor' => $s->anchor, 'is_enabled' => true, 'data' => $s->data])->all();
        $sections[1]['data']['image']['media_id'] = $m->id;
        $sections[1]['data']['image']['alt'] = 'Children painting';
        $this->put("/admin/pages/{$page->id}", ['title' => 'About Us', 'slug' => 'about', 'template' => 'inner', 'robots' => 'index, follow', 'sections' => json_encode($sections), 'action' => 'publish'])->assertRedirect();

        $html = $this->get('/about')->getContent();
        $this->assertStringContainsString($m->url(), $html);
        $this->assertMatchesRegularExpression('/srcset="[^"]*w320\.webp 320w/', $html);
        $this->assertStringContainsString('alt="Children painting"', $html);
        $this->assertStringContainsString('width="782" height="489"', $html);   // design width kept, height from new ratio

        $this->delete("/admin/media/{$m->id}")->assertSessionHas('error');
        $this->assertModelExists($m);

        $this->post("/admin/media/{$m->id}/replace", ['file' => $this->jpeg(800, 800, 'new.jpg')])->assertRedirect();
        $m->refresh();
        $this->assertSame([800, 800], [$m->width, $m->height]);
        $this->assertStringContainsString($m->url(), $this->get('/about')->getContent());
    }

    public function test_unused_media_can_be_deleted_with_files(): void
    {
        $this->admin();
        $this->upload($this->jpeg());
        $m = Media::with('variants')->first();
        $paths = array_merge([$m->path], $m->variants->pluck('path')->all());
        $this->delete("/admin/media/{$m->id}")->assertRedirect('/admin/media');
        $this->assertModelMissing($m);
        foreach ($paths as $p) {
            Storage::disk('public')->assertMissing($p);
        }
        Storage::disk('local')->assertMissing($m->original_path);
    }

    public function test_rename_alt_and_search(): void
    {
        $this->admin();
        $this->upload($this->jpeg());
        $m = Media::first();
        $this->put("/admin/media/{$m->id}", ['alt' => 'Girl with paint', 'title' => 'Painting', 'filename' => 'girl painting at easel'])->assertRedirect();
        $m->refresh();
        $this->assertSame('Girl with paint', $m->alt);
        $this->assertStringStartsWith('girl-painting-at-easel-', $m->filename);
        Storage::disk('public')->assertExists($m->path);
        $this->getJson('/admin/media/picker?search=easel')->assertJsonCount(1, 'items');
        $this->getJson('/admin/media/picker?search=nothing-here')->assertJsonCount(0, 'items');
    }

    public function test_editor_can_pick_but_permissions_apply(): void
    {
        $this->admin('editor')->getJson('/admin/media/picker')->assertOk();
    }

    public function test_import_command_moves_design_photos_into_library(): void
    {
        $this->artisan('cms:import-images')->assertSuccessful();
        $this->assertGreaterThan(30, Media::count());
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('/storage/media/', $html);
        $this->assertStringContainsString('srcset=', $html);
    }
}
