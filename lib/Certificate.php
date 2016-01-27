<?php

namespace Kelunik\Certificate;

class Certificate {
    private $pem;
    private $info;
    private $issuer;
    private $subject;

    public function __construct($pem) {
        if (!is_string($pem)) {
            throw new \InvalidArgumentException("Invalid variable type: Expected string, got " . gettype($pem));
        }

        if (!$cert = @openssl_x509_read($pem)) {
            throw new \InvalidArgumentException("Invalid PEM encoded certificate!");
        }

        $this->pem = $pem;

        if (!$this->info = openssl_x509_parse($cert)) {
            throw new \InvalidArgumentException("Invalid PEM encoded certificate!");
        }
    }

    public function getNames() {
        $san = isset($this->info["extensions"]["subjectAltName"]) ? $this->info["extensions"]["subjectAltName"] : "";
        $names = [];

        $parts = array_map("trim", explode(",", $san));

        foreach ($parts as $part) {
            if (stripos($part, "dns:") === 0) {
                $names[] = substr($part, 4);
            }
        }

        $names = array_map("strtolower", $names);
        $names = array_unique($names);

        sort($names);

        return $names;
    }

    public function getSubject() {
        if ($this->subject === null) {
            $this->subject = new Profile(
                isset($this->info["subject"]["CN"]) ? $this->info["subject"]["CN"] : null,
                isset($this->info["subject"]["O"]) ? $this->info["subject"]["O"] : null,
                isset($this->info["subject"]["C"]) ? $this->info["subject"]["C"] : null
            );
        }

        return $this->subject;
    }

    public function getIssuer() {
        if ($this->issuer === null) {
            $this->issuer = new Profile(
                isset($this->info["issuer"]["CN"]) ? $this->info["issuer"]["CN"] : null,
                isset($this->info["issuer"]["O"]) ? $this->info["issuer"]["O"] : null,
                isset($this->info["issuer"]["C"]) ? $this->info["issuer"]["C"] : null
            );
        }

        return $this->issuer;
    }

    public function getSerialNumber() {
        return $this->info["serialNumber"];
    }

    public function getValidFrom() {
        return $this->info["validFrom_time_t"];
    }

    public function getValidTo() {
        return $this->info["validTo_time_t"];
    }

    public function getSignatureType() {
        return $this->info["signatureTypeSN"];
    }

    public function isSelfSigned() {
        return $this->info["subject"] === $this->info["issuer"];
    }

    public function toPem() {
        return $this->pem;
    }

    public function toDer() {
        return self::pemToDer($this->pem);
    }

    public function __toString() {
        return $this->pem;
    }

    public function __debugInfo() {
        return [
            "commonName" => $this->getSubject()->getCommonName(),
            "names" => $this->getNames(),
            "issuedBy" => $this->getIssuer()->getCommonName(),
            "validFrom" => date("d.m.Y", $this->getValidFrom()),
            "validTo" => date("d.m.Y", $this->getValidTo()),
        ];
    }

    public static function derToPem($der) {
        return sprintf(
            "-----BEGIN CERTIFICATE-----\n%s-----END CERTIFICATE-----",
            chunk_split(base64_encode($der), 64, "\n")
        );
    }

    public static function pemToDer($pem) {
        $pattern = "@-----BEGIN CERTIFICATE-----\n([a-zA-Z0-9+/=\n]+)-----END CERTIFICATE-----@";

        if (!preg_match($pattern, $pem, $match)) {
            throw new \RuntimeException("Invalid PEM could not be converted to DER format.");
        }

        return base64_decode(str_replace(["\n", "\r"], "", trim($match[1])));
    }
}
