<?php

return function (
    $country,
    $state,
    $city,
    $company,
    $section,
    $domain,
    $email,
    $passphrase = null,
    $path = null
) {
    if (!extension_loaded('openssl')) {
        throw new LogicException('The OpenSSL extension must be loaded to create a certificate.');
    }

    $dn = [
        'countryName' => $country,
        'stateOrProvinceName' => $state,
        'localityName' => $city,
        'organizationName' => $company,
        'organizationalUnitName' => $section,
        'commonName' => $domain,
        'emailAddress' => $email
    ];

    $privkey = openssl_pkey_new(['private_key_bits' => 2048]);
    $cert = openssl_csr_new($dn, $privkey);
    $cert = openssl_csr_sign($cert, null, $privkey, 365);

    openssl_x509_export($cert, $cert);

    if (!is_null($passphrase)) {
        openssl_pkey_export($privkey, $privkey, $passphrase);
    } else {
        openssl_pkey_export($privkey, $privkey);
    }

    $pem = $cert . $privkey;

    if (is_null($path)) {
        return $pem;
    }

    return file_put_contents($path, $pem);
};
