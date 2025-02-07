<section x-data 
         x-bind:class="{ 'cursor-wait': $store.passkey.working }"
>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Passkeys') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('If you have a compatible device, you can enable passwordless authentication (Face ID, Windows Hello or Biometric Authentication). To do this, you need to enable Passwordless Sign In on this device.') }}
        </p>
    </header>

    <div class="mt-4 shadow-sm rounded-lg">
        <x-secondary-button 
            x-bind:disabled="$store.passkey.working"
            x-bind:class="{ 'opacity-25 cursor-not-allowed': $store.passkey.working }"
            x-on:click="startWebauthnPasskeyRegistration()"
        >
            {{ __('Add Pass Key') }}
        </x-secondary-button>

        <!-- Error Message -->
        <template x-if="$store.passkey.registration_error">
            <div class="text-red-500 mt-2">
                <p x-text="$store.passkey.registration_error"></p>
            </div>
        </template>
    </div>

    <div class="mt-6 bg-gray-300 dark:bg-gray-700 shadow-sm rounded-lg divide-y">
        @forelse ($passkeys as $k => $passkey)
            <div class="flex-1 p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-gray-800 dark:text-gray-200">{{ $passkey->name ?? "Passkey #{$k}" }}</span>
                        @if($passkey->last_used_at)
                            <small class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                                {{ $passkey->last_used_at }}
                            </small>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-4 text-gray-500">
                {{ __('No passkeys registered yet.') }}
            </div>
        @endforelse
    </div>
</section>