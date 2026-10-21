@include('errors.minimal-page', ['code' => 403, 'title' => 'Not allowed', 'text' => $exception?->getMessage() ?: 'You do not have permission to view this page.'])
