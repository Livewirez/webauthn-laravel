export type PublicKeyCredentialSource = {
    id: number,
    name?: string|null,
    public_key_credential_id: Base64URLString,
    public_key_credential_id_hex: string,
    credential_public_key: string,
    aaguid: string,
    user_handle: string,
    counter: number,
    other_ui?: string[],
    backup_eligible: boolean,
    backup_status: boolean,
    usage_count: number,
    last_used_at?: string
}