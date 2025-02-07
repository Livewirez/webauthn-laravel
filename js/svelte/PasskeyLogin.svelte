<script lang="ts">
	import { startRegistration, startAuthentication } from '@simplewebauthn/browser';
	import { writable } from 'svelte/store';
	import axios from 'axios';

	let working = $state(false);
	let authentication_error = $state<string | null>(null);

	async function startWebauthnLogin() {
		try {
			// Initialize store state
			working = true;
			authentication_error = null;

			// Fetch authentication options
			const response = await axios.get('/passkeys/generate-authentication-options');
			console.log('Response:Login Options', response);

			// Parse authentication options
			const publicKeyCredentialRequestOptions = JSON.parse(
				response.data.publicKeyCredentialRequestOptions
			);

			// Start authentication process
			const authResponse = await startAuthentication(
				publicKeyCredentialRequestOptions
			);

			// Log authentication response for debugging
			console.log('Authentication Response:', {
				raw: authResponse,
				serialized: JSON.stringify(authResponse)
			});

			// Verify authentication with server
			const verificationResponse = await axios.post('/passkeys/verify-authentication', {
				credentials: JSON.stringify(authResponse),
				credentials_id: authResponse.id
			});

			console.log('verificationResponse', verificationResponse);

			// Handle successful login
			if (verificationResponse.data.redirect) {
				window.location.href = verificationResponse.data.redirect;
			}

			return verificationResponse.data;
		} catch (error) {
			handleAuthenticationError(error);
		} finally {
			working = false;
		}
	}

	function handleAuthenticationError(error: any) {
		let errorMessage = 'Authentication failed';

		if (error.response?.data) {
			// Handle structured error responses
			errorMessage = extractErrorMessage(error.response.data);
		} else if (error.name === 'NotAllowedError') {
			errorMessage = 'Authentication was declined or timed out';
		} else if (error.name === 'SecurityError') {
			errorMessage = 'A security error occurred';
		} else {
			errorMessage = error.message || 'An unknown error occurred';
		}

		authentication_error = errorMessage;
	}

	function extractErrorMessage(responseData: any): string {
		if (responseData.credentials) {
			return Array.isArray(responseData.credentials) 
				? responseData.credentials[0] 
				: responseData.credentials;
		}
		return responseData.message || 'Authentication failed';
	}
</script>

<div>
	<section class={working ? 'cursor-wait' : ''}>
		<div class="mt-4 shadow-sm rounded-lg">
			<button 
				type="button" 
				disabled={working} 
				on:click={startWebauthnLogin} 
				class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150"
				class:opacity-25={working}
				class:cursor-not-allowed={working}
			>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
					<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
				</svg>
				
				Login with Passkey
			</button>
		</div>

		{#if authentication_error}
			<div class="text-red-500 mt-2 shadow-sm rounded-lg">
				<p>{authentication_error}</p>
			</div>
		{/if}
	</section>
</div>