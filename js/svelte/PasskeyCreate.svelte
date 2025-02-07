<script lang="ts">
	import { startRegistration } from '@simplewebauthn/browser';
	import axios from 'axios';

	// Define props with TypeScript
	type Passkey = {
		name?: string;
		last_used_at?: string;
	};

	let { passkeys = [] } = $props<{ passkeys?: Passkey[] }>();

	let working = $state(false);
	let registration_error = $state<string | null>(null);

	async function startWebauthnPasskeyRegistration() {
		try {
			working = true;
			registration_error = null;

			// Fetch registration options
			const response = await axios.get('/passkeys/generate-registration-options');
			console.log('Response:Registration Options', response);

			// Parse the public key credential creation options
			const publicKeyCredentialCreationOptions = JSON.parse(
				response.data.publicKeyCredentialCreationOptions
			);
			
			// Start registration process
			const attResp = await startRegistration({ 
				optionsJSON: publicKeyCredentialCreationOptions 
			});

			const r = {
				attResp: attResp,
				attRespJSON: JSON.stringify(attResp)
			};

			console.log('AttestationResponse: ', r);
			
			// Send the response back to the server for verification
			const verificationResponse = await axios.post('/passkeys/verify-registration', { 
				name: '', 
				credentials: JSON.stringify(attResp) 
			});
			
			console.log('VerificationResponse: ', verificationResponse);

			// Reload page on successful registration
			window.location.reload();

			return verificationResponse?.data;
		} catch (error: any) {
			// Handle specific error types
			let errorMessage = 'Unknown error occurred';
			
			if (error.name === 'InvalidStateError') {
				errorMessage = 'Authenticator was probably already registered';
			} else if (error.response) {
				// Server responded with an error
				errorMessage = error.response.data.message || 'Server error during registration';
			} else if (error.request) {
				// Request made but no response received
				errorMessage = 'No response from server';
			} else {
				// Something happened in setting up the request
				errorMessage = error.message;
			}

			registration_error = errorMessage;
			throw error;
		} finally {
			working = false;
		}
	}
</script>

<div>
	<section class={working ? 'cursor-wait' : ''}>
		<header>
			<h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
				Passkeys
			</h2>
			<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
				If you have a compatible device, you can enable passwordless authentication (Face ID, Windows Hello or Biometric Authentication). To do this, you need to enable Passwordless Sign In on this device.
			</p>
		</header>

		<div class="mt-4 shadow-sm rounded-lg">
			<button 
				type="button" 
				disabled={working} 
				on:click={startWebauthnPasskeyRegistration} 
				class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150"
				class:opacity-25={working}
				class:cursor-not-allowed={working}
			>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
					<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
				</svg>
				
				Add Passkey
			</button>

			{#if registration_error}
				<div class="text-red-500 mt-2">
					<p>{registration_error}</p>
				</div>
			{/if}
		</div>

		<div class="mt-6 bg-gray-300 dark:bg-gray-700 shadow-sm rounded-lg divide-y">
			{#if passkeys.length > 0}
				{#each passkeys as passkey, i}
					<div class="flex-1 p-4">
						<div class="flex justify-between items-center">
							<div>
								<span class="text-gray-800 dark:text-gray-200">
									{passkey.name ?? `Passkey #${i}`}
								</span>
							
								{#if passkey.last_used_at}
									<small class="ml-2 text-sm text-gray-600 dark:text-gray-300">
										{passkey.last_used_at}
									</small>
								{/if}
							</div>
						</div>
					</div>
				{/each}
			{:else}
				<div class="p-4 text-gray-500">
					No passkeys registered yet.
				</div>
			{/if}
		</div>
	</section>
</div>