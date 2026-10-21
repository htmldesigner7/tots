<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pages.edit');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasPermission('pages.edit');
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->hasPermission('pages.publish');
    }

    public function delete(User $user, Page $page): bool
    {
        // the home page cannot be deleted (it would take the site offline)
        return $user->hasPermission('pages.delete') && ! $page->isHome();
    }

    public function editSeo(User $user, Page $page): bool
    {
        return $user->hasPermission('seo.edit');
    }
}
