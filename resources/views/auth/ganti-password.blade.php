<x-app-layout>
    <h1>Ganti Password</h1>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        @method('PUT')

        <div>
            <label for="current_password">Kata sandi saat ini</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password">
            @error('current_password', 'updatePassword')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password">Kata sandi baru</label>
            <input id="password" type="password" name="password" autocomplete="new-password">
            @error('password', 'updatePassword')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation">Konfirmasi kata sandi baru</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
            @error('password_confirmation', 'updatePassword')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">Simpan</button>
    </form>
</x-app-layout>
