<x-layouts.app title="Modifier le restaurant">
    <h1>Modifier {{ $restaurant->name }}</h1>
    <form method="post" action="{{ route('owner.restaurants.update', $restaurant) }}">
        @csrf @method('PUT')
        <label>Nom <input name="name" required value="{{ old('name', $restaurant->name) }}"></label>
        <label>Description <textarea name="description">{{ old('description', $restaurant->description) }}</textarea></label>
        <label>Téléphone <input name="phone" value="{{ old('phone', $restaurant->phone) }}"></label>
        <label>Adresse <input name="address" value="{{ old('address', $restaurant->address) }}"></label>
        <button type="submit">Enregistrer</button>
    </form>
</x-layouts.app>
