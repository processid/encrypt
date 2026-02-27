<?php
    // Chiffrement/Déchiffrement de fichier avec OpenSSL
    // -------------------
    // -- Instanciation --
    // -------------------
    // $obj = new EncryptFileOpenSSL($key_aes256, $key_hash512, $method);
    // $key_aes256 = <key aes256>
    // $key_hash512 = <key hash512>
    // $method = <'aes-128-cbc' | 'aes-256-cbc' | ...>
    
    namespace processid\encrypt;
    
    class EncryptFileOpenSSL
    {
        private string $_password;
        private string $_method;
        
        static $FILE_ENCRYPTION_BLOCKS = 10000;
        
        public function __construct(string $password, string $method)
        {
            $this->SetPassword($password);
            $this->SetMethod($method);
        }
        
        public function SetPassword($password): EncryptFileOpenSSL
        {
            $this->_password = $password;
            return $this;
        }
        
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
        
        function encrypt_file($file_in, $file_out): bool
        {
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
        
        function decrypt_file($file_in, $file_out): bool
        {
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
