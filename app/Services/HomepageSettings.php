<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class HomepageSettings
{
    public const SETTINGS_KEY = 'homepage';

    public function get(): array
    {
        return Cache::rememberForever('homepage.settings', fn (): array => $this->normalize((array) (Setting::query()->where('key', self::SETTINGS_KEY)->value('value') ?? [])));
    }

    public function save(array $value): void
    {
        Setting::updateOrCreate(['key' => self::SETTINGS_KEY], ['group' => 'content', 'value' => $this->normalize($value)]);
    }

    public function forget(): void { Cache::forget('homepage.settings'); }

    public function normalize(array $value): array { return self::mergeKnown(self::defaults(), $value); }

    private static function mergeKnown(array $defaults, array $value): array
    {
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $value)) continue;
            $defaults[$key] = is_array($default) && is_array($value[$key])
                ? self::mergeKnown($default, $value[$key])
                : $value[$key];
        }
        return $defaults;
    }

    public static function defaults(): array
    {
        return ['seo'=>['title'=>'Top Halal — Trouver un restaurant halal','description'=>'Trouvez un restaurant halal près de chez vous.'],'hero'=>['eyebrow'=>'Le guide des bonnes adresses','title'=>'Trouvez votre restaurant halal, simplement.','text'=>'Recherchez une adresse par ville, spécialité ou nom.'],'restaurant_block'=>['enabled'=>true,'eyebrow'=>'','title'=>'Trouvez facilement un restaurant halal','text'=>"À la recherche d'un restaurant halal près de chez vous ? Top Halal vous aide à découvrir des adresses partout en France selon votre ville, vos envies ou votre spécialité préférée. Fast-food, cuisine indienne, africaine, asiatique, grillades, brunch ou gastronomie : explorez les restaurants et trouvez plus facilement l'adresse qui vous correspond."],'guide'=>['eyebrow'=>'Actualités & conseils','title'=>'Le guide Top Halal'],'editorial_block'=>['enabled'=>true,'eyebrow'=>'','title'=>'Le halal au quotidien, et bien plus encore','text'=>"Top Halal, c'est aussi un espace consacré à l'actualité, au halal, à l'islam et à la vie quotidienne des musulmans. Retrouvez nos articles autour du Ramadan, de la pratique religieuse, de la consommation halal, des tendances, des initiatives et des sujets qui rythment la vie de la communauté musulmane."],'why'=>['title'=>'Pourquoi Top Halal ?','cards'=>[['title'=>'Des adresses partout en France','text'=>'Découvrez des restaurants halal dans de nombreuses villes et trouvez facilement une adresse près de chez vous.'],['title'=>'Toutes vos envies','text'=>'Fast-food, grillades, cuisine indienne, africaine, asiatique, brunch, pizza ou gastronomie : explorez les spécialités qui vous font envie.'],['title'=>'Des infos utiles','text'=>'Retrouvez sur chaque fiche les informations disponibles pour préparer votre visite : adresse, horaires, services, photos et avis.'],['title'=>'Le halal avec transparence','text'=>"Lorsqu'une information de certification est disponible, elle peut être indiquée sur la fiche afin de vous aider à faire votre choix."]]],'transparency_block'=>['enabled'=>true,'eyebrow'=>'','title'=>'Des informations pour mieux choisir','text'=>"Consultez les informations disponibles sur chaque établissement : adresse, horaires, spécialités, services, photos et avis. Lorsqu'une certification halal est renseignée, nous l'indiquons également. Top Halal n'étant pas un organisme de certification, nous vous recommandons de vérifier directement auprès du restaurant les informations importantes pour vous."],'submission_cta'=>['title'=>'Vous connaissez une bonne adresse ?','text'=>'Proposez un restaurant pour la faire découvrir à toute la communauté.']];
    }
}
