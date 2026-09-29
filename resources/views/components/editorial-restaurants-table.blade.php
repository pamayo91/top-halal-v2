@php($addresses = app(\App\Services\RestaurantAddressPresenter::class))
<section class="editorial-restaurants-table" aria-label="Restaurants recommandés">
    <table>
        <caption class="sr-only">Restaurants recommandés</caption>
        <thead>
            <tr>
                <th scope="col">Restaurant</th>
                <th scope="col">Ville</th>
                <th scope="col">Adresse</th>
                <th scope="col">Certification</th>
            </tr>
        </thead>
        <tbody>
            @foreach($restaurants as $restaurant)
                <tr>
                    <th scope="row"><a href="{{ route('restaurants.show', $restaurant->slug) }}">{{ $restaurant->name }}</a></th>
                    <td>{{ $restaurant->city_name }}</td>
                    <td>{{ $addresses->structuredLine($restaurant) }}</td>
                    <td>Halal ✓</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <ul class="editorial-restaurants-table-mobile" aria-label="Restaurants recommandés">
        @foreach($restaurants as $restaurant)
            <li>
                <a href="{{ route('restaurants.show', $restaurant->slug) }}">{{ $restaurant->name }}</a>
                <dl>
                    <div><dt>Ville</dt><dd>{{ $restaurant->city_name }}</dd></div>
                    <div><dt>Adresse</dt><dd>{{ $addresses->structuredLine($restaurant) }}</dd></div>
                    <div><dt>Certification</dt><dd>Halal ✓</dd></div>
                </dl>
            </li>
        @endforeach
    </ul>
</section>
