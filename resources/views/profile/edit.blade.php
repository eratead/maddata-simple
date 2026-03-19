<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">Profile</h1>
@endpush

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        <x-page-box class="p-6">
            @include('profile.partials.update-profile-information-form')
        </x-page-box>

        <div class="flex flex-col gap-6">
            <x-page-box class="p-6">
                @include('profile.partials.update-password-form')
            </x-page-box>

            <x-page-box class="p-6">
                @include('profile.partials.delete-user-form')
            </x-page-box>
        </div>

    </div>

</x-app-layout>
