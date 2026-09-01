<?php
    // Chiffrement/Déchiffrement de fichier avec OpenSSL
    // -------------------
    // -- Instanciation --
    // -------------------
    // $obj = new EncryptFileOpenSSL($password, $method);
    // $password = <mot de passe>
    // $method = <'aes-128-cbc' | 'aes-256-cbc' | ...>
    
    namespace processid\encrypt;
    
    class EncryptFileOpenSSL
    {
        private string $_password;
        private string $_method;
        
        public static $FILE_ENCRYPTION_BLOCKS = 10000;
        
        public function __construct(string $password, string $method)
        {
            $this->SetPassword($password);
            $this->SetMethod($method);
        }
        
        /** @param string|int|float|bool $password */
        public function SetPassword($password): EncryptFileOpenSSL
        {
            $this->_password = $password;
            return $this;
        }
        
        /** @param string|int|float|bool $method */
        public function SetMethod($method): EncryptFileOpenSSL
        {
            $this->_method = $method;
            return $this;
        }
        
        private function password(): string
        {
            return $this->_password;
        }
        
        private function method(): string
        {
            return $this->_method;
        }
        
        /**
         * @param string|null $file_in
         * @param string|null $file_out
         */
        function encrypt_file($file_in, $file_out): bool
        {
            // Evite la deprecation "Passing null to parameter of type string"
            // de fopen() ; fopen('') leve la meme ValueError qu'auparavant.
            $file_in ??= '';
            $file_out ??= '';
            
            $iv_length = openssl_cipher_iv_length($this->method());
            $key = substr(sha1($this->password(), true), 0, 16);
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            $error = false;
            if ($fpOut = fopen($file_out, 'w')) {
                // Enregistrement du vecteur d'initialisation au début du fichier
                fwrite($fpOut, $iv);
                if ($fpIn = fopen($file_in, 'rb')) {
                    while (!feof($fpIn)) {
                        $plaintext = fread($fpIn, 16 * self::$FILE_ENCRYPTION_BLOCKS);
                        $ciphertext = openssl_encrypt($plaintext, $this->method(), $key, OPENSSL_RAW_DATA, $iv);
                        // On utilise les $iv_length octets de ciphertext comme prochain vecteur d'initialisation
                        $iv = substr($ciphertext, 0, $iv_length);
                        fwrite($fpOut, $ciphertext);
                    }
                    fclose($fpIn);
                } else {
                    $error = true;
                }
                fclose($fpOut);
            } else {
                $error = true;
            }
            
            return !$error;
        }
        
        /**
         * @param string|null $file_in
         * @param string|null $file_out
         */
        function decrypt_file($file_in, $file_out): bool
        {
            $file_in ??= '';
            $file_out ??= '';
            
            $iv_length = openssl_cipher_iv_length($this->method());
            $key = substr(sha1($this->password(), true), 0, 16);
            
            $error = false;
            if ($fpOut = fopen($file_out, 'w')) {
                if ($fpIn = fopen($file_in, 'rb')) {
                    // Lecture du vecteur d'initialisation au début du fichier
                    $iv = fread($fpIn, $iv_length);
                    while (!feof($fpIn)) {
                        // Il faut lire un bloc de plus pour déchiffrer que pour chiffrer
                        $ciphertext = fread($fpIn, 16 * (self::$FILE_ENCRYPTION_BLOCKS + 1));
                        $plaintext = openssl_decrypt($ciphertext, $this->method(), $key, OPENSSL_RAW_DATA, $iv);
                        // On utilise les $iv_length octets de ciphertext comme prochain vecteur d'initialisation
                        $iv = substr($ciphertext, 0, $iv_length);
                        fwrite($fpOut, $plaintext);
                    }
                    fclose($fpIn);
                } else {
                    $error = true;
                }
                fclose($fpOut);
            } else {
                $error = true;
            }
            
            return !$error;
        }
    }
