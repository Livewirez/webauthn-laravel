import './bootstrap';

import Alpine from 'alpinejs';

import { startRegistration, startAuthentication } from '@simplewebauthn/browser';

window.Alpine = Alpine;

function showAlert(message) {
    alert(`Message: ${message}`)
} 

window.showAlert = showAlert;

function startWebauthnPasskeyRegistration() {
    return new Promise((resolve, reject) => {
        // Set working state
        Alpine.store('passkey', { 
            working: true, 
            registration_error: null 
        });

        // Fetch registration options
        axios.get('/passkeys/generate-registration-options')
            .then(async (response) => {
                console.log('Response:Registration Options', response)
                try {
                    // Parse the public key credential creation options
                    const publicKeyCredentialCreationOptions = JSON.parse(response.data.publicKeyCredentialCreationOptions);
                    
                    // Start registration process
                    const attResp = await startRegistration({ optionsJSON: publicKeyCredentialCreationOptions });

                    const r = {
                        attResp: attResp,
                        attRespJSON: JSON.stringify(attResp)
                    }

                    console.log('AttestationResponse: ', r)
                    
                    // Send the response back to the server for verification
                    const verificationResponse = await axios.post('/passkeys/verify-registration', { name: '', credentials: JSON.stringify(attResp) });
                    
                    // Reset working state and resolve
                    Alpine.store('passkey', { 
                        working: false, 
                        registration_error: null 
                    });


                    console.log('VerificationResponse: ', verificationResponse)

                    resolve(verificationResponse?.data);

                    window.location.reload();
                } catch (error) {
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
                    
                    // Update Alpine store with error
                    Alpine.store('passkey', { 
                        working: false, 
                        registration_error: errorMessage 
                    });
                    
                    reject(error);
                }
            })
            .catch(error => {
                // Handle axios request error
                Alpine.store('passkey', { 
                    working: false, 
                    registration_error: 'Failed to fetch registration options' 
                });
                
                reject(error);
            });
    });
}

function startWebauthnLogin() {
    return new Promise((resolve, reject) => {
        // Initialize store state
        Alpine.store('passkey_login', {
            working: true,
            authentication_error: null
        });

        // Fetch authentication options
        axios.get('/passkeys/generate-authentication-options')
            .then(async (response) => {
                console.log('Response:Login Options', response) 

                try {
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

                    // Reset store state on success
                    Alpine.store('passkey_login', {
                        working: false,
                        authentication_error: null
                    });

                    console.log('verificationResponse', verificationResponse) 

                    // Handle successful login
                    if (verificationResponse.data.redirect) {
                        window.location.href = verificationResponse.data.redirect;
                    }

                    resolve(verificationResponse.data);

                } catch (error) {
                    handleAuthenticationError(error, reject);
                }
            })
            .catch(error => handleNetworkError(error, reject));
    });
}
            

function handleAuthenticationError(error, reject) {
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

    updateStoreWithError(errorMessage);
    reject(error);
}

function handleNetworkError(error, reject) {
    console.error('Network error during authentication:', error);
    
    const errorMessage = 'Unable to connect to authentication service. Please try again.';
    updateStoreWithError(errorMessage);
    reject(error);
}


function extractErrorMessage(responseData) {
    if (responseData.credentials) {
        return Array.isArray(responseData.credentials) 
            ? responseData.credentials[0] 
            : responseData.credentials;
    }
    return responseData.message || 'Authentication failed';
}

function updateStoreWithError(errorMessage) {
    Alpine.store('passkey_login', {
        working: false,
        authentication_error: errorMessage
    });
}


// Blade Template
Alpine.store('passkey', {
    working: false,
    registration_error: null
});

Alpine.store('passkey_login', { 
    working: false, 
    authentication_error: null 
});

window.startWebauthnPasskeyRegistration = startWebauthnPasskeyRegistration;

window.startWebauthnLogin = startWebauthnLogin;

Alpine.start();