<?php

use App\Models\Post;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects root to default locale', function () {
    $this->get('/')->assertRedirect();
});

it('renders home in pt', function () {
    $project = Project::query()->create([
        'slug'            => 'sample-plugin',
        'name'            => 'sample-plugin',
        'category'        => 'filament_plugin',
        'description'     => ['pt' => 'Plugin de exemplo.', 'en' => 'Sample plugin.'],
        'versions'        => ['v5'],
        'stack'           => ['Filament'],
        'stars'           => 10,
        'downloads'       => 1000,
        'downloads_label' => '1k',
        'status'          => 'published',
        'featured'        => true,
        'published_at'    => now(),
    ]);

    $response = $this->get('/pt');

    $response->assertOk();
    $response->assertSee('sample-plugin');
});

it('renders home in en', function () {
    $this->get('/en')->assertOk();
});

it('renders about page', function () {
    $this->get('/pt/sobre')->assertOk();
    $this->get('/en/sobre')->assertOk();
});

it('renders projects index', function () {
    $this->get('/pt/projetos')->assertOk();
});

it('filters projects by category', function () {
    Project::query()->create([
        'slug'         => 'a-plugin',
        'name'         => 'a-plugin',
        'category'     => 'filament_plugin',
        'description'  => ['pt' => 'A.', 'en' => 'A.'],
        'status'       => 'published',
        'published_at' => now(),
    ]);
    Project::query()->create([
        'slug'         => 'b-package',
        'name'         => 'b-package',
        'category'     => 'laravel_package',
        'description'  => ['pt' => 'B.', 'en' => 'B.'],
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/pt/projetos?cat=filament_plugin')
        ->assertOk()
        ->assertSee('a-plugin')
        ->assertDontSee('b-package');
});

it('renders project show', function () {
    Project::query()->create([
        'slug'         => 'my-plugin',
        'name'         => 'my-plugin',
        'category'     => 'filament_plugin',
        'description'  => ['pt' => 'Descrição.', 'en' => 'Description.'],
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/pt/projetos/my-plugin')->assertOk()->assertSee('my-plugin');
});

it('404s on draft project show', function () {
    Project::query()->create([
        'slug'        => 'draft-plugin',
        'name'        => 'draft-plugin',
        'category'    => 'filament_plugin',
        'description' => ['pt' => 'X.'],
        'status'      => 'draft',
    ]);

    $this->get('/pt/projetos/draft-plugin')->assertNotFound();
});

it('renders open-source page', function () {
    $this->get('/pt/open-source')->assertOk();
});

it('renders blog index', function () {
    $this->get('/pt/blog')->assertOk();
});

it('renders blog show', function () {
    Post::query()->create([
        'slug'         => 'my-post',
        'title'        => ['pt' => 'Meu post'],
        'excerpt'      => ['pt' => 'Resumo.'],
        'body'         => ['pt' => 'Corpo.'],
        'tags'         => ['Filament'],
        'reading_time' => 5,
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/pt/blog/my-post')->assertOk()->assertSee('Meu post');
});

it('renders contact page', function () {
    $this->get('/pt/contato')->assertOk();
});

it('renders sponsors page', function () {
    $this->get('/pt/sponsors')->assertOk();
});

it('rejects invalid locale', function () {
    $this->get('/fr')->assertNotFound();
});
