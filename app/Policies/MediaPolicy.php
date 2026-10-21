<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        // anyone who edits pages may pick images; managing the library needs media.manage
        return $user->hasPermission('media.manage') || $user->hasPermission('pages.edit') || $user->hasPermission('settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('media.manage');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->hasPermission('media.manage');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->hasPermission('media.manage');
    }
}
