<?php
    
    // Chiffrement/Déchiffrement avec OpenSSL
    // Compatible avec chiffre_chaine() et dechiffre_chaine() de WD
    // Les clef doivent être préalablement générées avec:
    // $key_aes256 = base64_encode(openssl_random_pseudo_bytes(32));
    // $key_hash512 = base64_encode(openssl_random_pseudo_bytes(64));
    // Les données ($data) doivent être en UTF8
    // -------------------
    // -- Instanciation --
    // -------------------
    // $obj = new EncryptOpenSSL($key_aes256, $key_hash512, $method);
    // $key_aes256 = <key aes256>
    // $key_hash512 = <key hash512>
    // $method = <'aes-128-cbc' | 'aes-256-cbc' | ...>
    namespace processid\encrypt;
    
    class EncryptOpenSSL
    {
        private string $_key_aes256;
        private string $_key_hash512;
        private string $_method;
        
        function __construct(string $key_aes256, string $key_hash512, string $method)
        {
            $this->SetKey_aes256($key_aes256);
            $this->SetKey_hash512($key_hash512);
            $this->SetMethod($method);
        }
        
        function SetKey_aes256(string $key_aes256): EncryptOpenSSL
        {
            $this->_key_aes256 = $key_aes256;
            
            return $this;
        }
        
        function SetKey_hash512(string $key_hash512): EncryptOpenSSL
        {
            $this->_key_hash512 = $key_hash512;
            return $this;
        }
        
        function SetMethod(string $method): EncryptOpenSSL
        {
            $this->_method = $method;
            return $this;
        }
        
        private function key_aes256(): string
        {
            return $this->_key_aes256;
        }
        
        private function key_hash512(): string
        {
            return $this->_key_hash512;
        }
        
        private function method(): string
        {
            return $this->_method;
        }
        
        function encrypt_string($data): string
        {
            $key_aes256 = base64_decode($this->key_aes256());
            $key_hash512 = base64_decode($this->key_hash512());
            
            $iv_length = openssl_cipher_iv_length($this->method());
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            $first_encrypted = openssl_encrypt($data, $this->method(), $key_aes256, OPENSSL_RAW_DATA, $iv);
            $second_encrypted = hash_hmac('sha512', $first_encrypted, $key_hash512, TRUE);
            
            $output = base64_encode($iv . $second_encrypted . $first_encrypted);
            
            return $output;
        }
        
        function decrypt_string($data): string|false
        {
            if (empty($data)) {
                return '';
            }
            
            $key_aes256 = base64_decode($this->key_aes256());
            $key_hash512 = base64_decode($this->key_hash512());
            $mix = base64_decode($data);
            
            $iv_length = openssl_cipher_iv_length($this->method());
            
            if (strlen($mix) < $iv_length + 64) {
                // données trop courtes → corruption
                return false;
            }
            
            $iv = substr($mix, 0, $iv_length);
            $second_encrypted = substr($mix, $iv_length, 64);
            $first_encrypted = substr($mix, $iv_length + 64);
            
            $data = openssl_decrypt($first_encrypted, $this->method(), $key_aes256, OPENSSL_RAW_DATA, $iv);
            $second_encrypted_new = hash_hmac('sha512', $first_encrypted, $key_hash512, TRUE);

            if (hash_equals($second_encrypted, $second_encrypted_new)) {
                return $data;
            }
            return false;
        }
        
        static function generate_key_aes256(): string
        {
            return base64_encode(openssl_random_pseudo_bytes(32));
        }
        
        static function generate_key_hash512(): string
        {
            return base64_encode(openssl_random_pseudo_bytes(64));
        }
    }
