<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 100)->unique();
            $table->string('location', 40)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('label', 160);
            $table->string('link_type', 30);
            $table->nullableMorphs('linkable');
            $table->string('destination_key', 180)->nullable();
            $table->string('url', 2048)->nullable();
            $table->boolean('target_blank')->default(false);
            $table->boolean('nofollow')->default(false);
            $table->boolean('visible_desktop')->default(true);
            $table->boolean('visible_mobile')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });

        $now = now();
        $menus = [
            ['name' => 'Header principal', 'slug' => 'header-principal', 'location' => 'header_main'],
            ['name' => 'Footer - Restaurants', 'slug' => 'footer-restaurants', 'location' => 'footer_1'],
            ['name' => 'Footer - Découvrir', 'slug' => 'footer-decouvrir', 'location' => 'footer_2'],
            ['name' => 'Footer - Top-Halal', 'slug' => 'footer-top-halal', 'location' => 'footer_3'],
            ['name' => 'Footer - Informations', 'slug' => 'footer-informations', 'location' => 'footer_4'],
            ['name' => 'Footer - Légal', 'slug' => 'footer-legal', 'location' => 'footer_legal'],
        ];
        foreach ($menus as &$menu) { $menu['is_active'] = true; $menu['created_at'] = $now; $menu['updated_at'] = $now; }
        DB::table('menus')->insert($menus);
        $ids = DB::table('menus')->pluck('id', 'location');

        // These are the existing public route destinations. They deliberately
        // remain plain internal paths because they are not content models.
        $items = [
            ['menu_id' => $ids['header_main'], 'label' => 'Restaurants', 'url' => '/restaurants', 'sort_order' => 1],
            ['menu_id' => $ids['header_main'], 'label' => 'Villes', 'url' => '/restaurants', 'sort_order' => 2],
            ['menu_id' => $ids['header_main'], 'label' => 'Cuisines', 'url' => '/restaurants', 'sort_order' => 3],
            ['menu_id' => $ids['header_main'], 'label' => 'Guides', 'url' => '/blog', 'sort_order' => 4],
            ['menu_id' => $ids['header_main'], 'label' => 'Blog', 'url' => '/blog', 'sort_order' => 5],
            ['menu_id' => $ids['footer_1'], 'label' => 'Restaurants', 'url' => '/restaurants', 'sort_order' => 1],
            ['menu_id' => $ids['footer_1'], 'label' => 'Le guide', 'url' => '/blog', 'sort_order' => 2],
        ];
        foreach ($items as &$item) {
            $item += ['parent_id' => null, 'link_type' => 'internal_url', 'linkable_type' => null, 'linkable_id' => null, 'destination_key' => null, 'target_blank' => false, 'nofollow' => false, 'visible_desktop' => true, 'visible_mobile' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('menu_items')->insert($items);

        DB::table('settings')->updateOrInsert(['key' => 'header_navigation'], ['group' => 'navigation', 'value' => json_encode(['menu_id' => $ids['header_main'], 'account_visible' => true, 'account_label' => 'Mon compte', 'submission_visible' => true, 'submission_label' => 'Ajouter un restaurant'], JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now]);
        DB::table('settings')->updateOrInsert(['key' => 'footer_navigation'], ['group' => 'navigation', 'value' => json_encode(['introduction' => 'Le guide indépendant pour trouver un restaurant halal.', 'show_logo' => true, 'column_menu_ids' => [$ids['footer_1'], $ids['footer_2'], $ids['footer_3'], $ids['footer_4']], 'legal_menu_id' => $ids['footer_legal'], 'copyright' => '© Top-Halal', 'social_links' => []], JSON_THROW_ON_ERROR), 'updated_at' => $now, 'created_at' => $now]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['header_navigation', 'footer_navigation'])->delete();
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
