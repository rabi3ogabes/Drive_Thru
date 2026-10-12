<?php

it('renders the landing page with both sign-in routes', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(url('/app'), false)
        ->assertSee(url('/admin'), false)
        ->assertSee('hreflang="en"', false);
});

it('shows a branded, translated 404', function (string $locale, string $title) {
    $this->withHeader('Accept-Language', $locale)
        ->get('/this/page/does-not-exist')
        ->assertNotFound()
        ->assertSee($title)
        ->assertSee(__('errors.actions.home', [], $locale));
})->with([
    'arabic' => ['ar', 'لم نجد ما تبحث عنه'],
    'english' => ['en', 'We couldn’t find that'],
]);

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});
