<?php

namespace UnityWebPortal\lib;

class UnityGithub
{
    /** @return string[] */
    public function getSshPublicKeys(string $username): array
    {
        $url = "https://api.github.com/users/$username/keys";
        $headers = ["User-Agent: Unity HPC Account Portal"];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $curl_output = _curl_exec($curl);
        $keys = _json_decode($curl_output, false);
        curl_close($curl);

        // normally returns array of objects each with a ->key attribute
        // if bad URL or no such user, returns status=404 object
        // if no keys, returns []
        if (!is_array($keys) || count($keys) == 0) {
            return [];
        }
        // phpcs:disable
        return array_map(function ($x) {
            return $x->key;
        }, $keys);
        // phpcs:enable
    }
}
