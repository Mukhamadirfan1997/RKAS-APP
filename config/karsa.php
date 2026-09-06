<?php

$version = '0.0.0';
try {
    $tauriConf = base_path('src-tauri/tauri.conf.json');
    if (is_file($tauriConf)) {
        $json = json_decode((string) file_get_contents($tauriConf), true);
        if (isset($json['version']) && is_string($json['version'])) {
            $version = $json['version'];
        }
    }
} catch (\Throwable $e) {
    // fallback keep 0.0.0
}

return [
    'version' => env('KARSA_VERSION', $version),
];
