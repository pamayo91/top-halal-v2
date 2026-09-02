<x-layouts.app title="Modifier le restaurant">
    <main class="shell submission-page">
    <h1>Modifier {{ $restaurant->name }}</h1>
    <form class="submission-form" method="post" action="{{ route('owner.restaurants.update', $restaurant) }}">
        @csrf @method('PUT')
        <label>Nom <input name="name" required value="{{ old('name', $restaurant->name) }}"></label>
        <label>Description <textarea name="description">{{ old('description', $restaurant->description) }}</textarea></label>
        <label>Téléphone <input name="phone" value="{{ old('phone', $restaurant->phone) }}"></label>
        <input type="hidden" name="location_changed" value="0" data-location-changed>
        <x-address-selector
            label="Nouvelle adresse (facultatif)"
            :address-line1="$restaurant->address_line1"
            :postal-code="$restaurant->postal_code"
            :city-name="$restaurant->city_name"
            :latitude="$restaurant->latitude"
            :longitude="$restaurant->longitude"
            :show-existing="true"
        />
        <p class="form-help">Pour modifier l’adresse ou sa position, sélectionnez obligatoirement une suggestion, puis ajustez le marqueur si nécessaire.</p>
        <button type="submit">Enregistrer</button>
    </form>
    </main>
</x-layouts.app>
