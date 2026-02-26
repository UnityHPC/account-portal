<?php

use PHPUnit\Framework\Attributes\DataProvider;

class UtilsTest extends UnityWebPortalTestCase
{
    public static function SSHKeyProvider()
    {
        global $HTTP_HEADER_TEST_INPUTS;
        // these key types must all be in CONFIG["ldap"]["allowed_ssh_key_types"]
        $validKeys = [
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB+XqO25MUB9x/pS04I3JQ7rMGboWyGXh0GUzkOrTi7a",
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB+XqO25MUB9x/pS04I3JQ7rMGboWyGXh0GUzkOrTi7a foobar",
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB+XqO25MUB9x/pS04I3JQ7rMGboWyGXh0GUzkOrTi7a foo bar baz",
            "ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBF/dSI9/7YWeyB8wa4rEWRdeb9pQbrGxZwYFV2ulr0agXdbiJIApp0MWDYlIc9XI+4Y+cVAj66PQ2YaRz44BV+o=",
            "ecdsa-sha2-nistp384 AAAAE2VjZHNhLXNoYTItbmlzdHAzODQAAAAIbmlzdHAzODQAAABhBOr8ZnJPs/mP/1c74P8NsiPL2pq/vKo6u0vtkgqgyZjqJJpPS5rP6EFJkT8DI0Fx9/70jvyH8wGK6tx+/gNElMlZ6P2RyHbDvL4Nh2LAEW3BQ2lbULyElP/ZeXIEQzPxng==",
            "ecdsa-sha2-nistp521 AAAAE2VjZHNhLXNoYTItbmlzdHA1MjEAAAAIbmlzdHA1MjEAAACFBAFmNNrz+B6exxuReTXQJzXUzJ4zB5JTuB8Xtcr79P4tk4SlA5a5ufQlsqMdPRhA76KFaLmONGF1e+vwcQWsj/MbRQE0H56tkZRNa+ch5/YI6iKSffkzpRKogl/uTP4rlpRb1vppsURRYxQ2JBzLYolj8VUV+N0sCwM+8maiOGJYuc4dlQ==",
            "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAAAgQC6RjRhmaBfCN9l9qsnjplvatK/a7zb2tdbg7kDj8mWXfbC1zkELLLX/L+5hAySbm8QXPgr18CqcyV9LqK+vJ/aPHRNo3E/mp14pxp0nHpPlMzUV8ybl2uk2kBMXWRweOYfAcA5eJToHVAXJEVBvcwDI1WVG9Nfo5w1UhGSqcn4oQ==",
            implode("", [
                "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAAIAQDMHfRgu2HjTAODg+1yAXeZ",
                "alNrT3S0sXv7fqC9/uJW86AHU6l384TpSEoqVbl4cke8lev49ljsEg50Zppo",
                "C4fiP6+nAeBy609VWfcHBmbVDeVdLZiAh2XpNW3Fns6ecM24OPr7kdxuhV8p",
                "KTMupXYc/mEUdKTB7DiQcRWcLp8BhX14K3PuFbiprqnacoeiu1In9SLKZd2E",
                "4vg2TrhptdZuuav4WX0r2s2uUwAz+7jpXWYoXUUjfmImEg6h9ETCzKGFwHYA",
                "Tn879WW+28RUOIurfUUU5njnGmtVoWG1s0L7JpoJfu16ePdcPCEpn92coP8D",
                "pFw10iQwh4AsjIdEVYEYfjtxbdk5TqACkfgKo+h9G3nNXO6x8mGjhfNa6CHF",
                "+wVJ4RrJWdhBGfKog+CtD+NHDYXFbyciGT2CtGTFay182DdUg2MoXn4eSmPE",
                "qZJ+kHtJ0mwe0nKtLNwik4c/54X1rZLETauEFMKRE3/JSlAdAMm0jZNW7fad",
                "QOJHB63q/yxyCFLztLrtzhZlBH0DoGuGdHxznbKYDctcQypztP2aG4G+W1go",
                "zwNmwsJRLY3pWos377QRUOq0w3qvcw9BZwigfqixGWHbC2JUyxqoa4opez6z",
                "oDH+tjvJQ0iKv9DQrVOdh8e6NzHMlSnri3r34K5NzDIvV3uROO1yC7pbHogO",
                "S2XmO+nnJYlnINhitXe9+gcfkcEwZs14lxrxcAElJmJOSPE+uui80ZMUHd6C",
                "lemoptFE4cAhdczmkQXURbpzWguHRSWk+5/AXb5r1P7AYpaZSRFfOqy4oB/v",
                "6rKTBjplH7LYi3otykeB1PooHnUfpziOLLFq5ghVdCU8R10yE43drDRnu8dF",
                "pirxBF6AAUzQpUMbYae9BvVTPMWAyM2Wn5P9EUZ+hngvhDlyoZBoNCeqEeWN",
                "6l4KcQVPZwyg1b1PhkAtVzhss3mJQ0Xsqabp2cQvvj+Z/rfQyZKJlAiv2gKd",
                "0W+E5zTd0TqA50JZLKOtPhMsEXUqKop4H6OcJ+SDqqNWzGdnYJHYccQ7y/2I",
                "XrqBlW0gs6BX04Yx+5LusnLBKH8D2MB9kvASPKopzzcF2KFsIw0pLkEc0cVP",
                "oY5gwy05JTuKYoxzIbePgM8KV8rgQ1it442LHEAo5k/6GwVkl/6aQDCwmQV2",
                "YhBxfioyOZZLTCG95ANHaz19H7M+T4BY/d3lUD9FsFcPmY7Ikj6Ma0YMGmvg",
                "ghdIvaXmCxEyIQRi+lpcjPVHV3MzELgNTRDDVkM0TFXlGSBv63XRjos0kbDN",
                "kOo2wLmTModCFuudLDGxOjYriMKdkXmU4Tc7wZSGgngZch49u9b3A6RVwxa2",
                "0LUkuAXOS1EvyBqOcQ1m5RklzwPuK0FD+9qVNHPFSNpRsXbH/mljqlR8MYyd",
                "DGphZW5vPmJ1RGhO8EOkQIk6bZz46Y8U4fVsvSslBX1TWczmAZ6RPA/rFg9R",
                "KAehmze5GJLa0ypVcD86ILJftBd7a+Rzx7G9liLR5HTMv+3k/cbYLiahTQ33",
                "thHK0jiB2DLa0D2tXmQEdEHR1lHlGBwLr5XK+fOFbYKAyhIt63aEL9hmdUBL",
                "DFQfjBLbVEToGonSM54diks9Nesy2wrVYe4bWCmj+TCut0cDXtgQSxiSJhoD",
                "kS2gYIEX6Rrc4ETMhqtfG7LgH2wHeVvJ+wjT/uNQ+8c9Eft4/NZfVpK1vru1",
                "A31ZGqlZKuBxnO4Cd6PiwzBk2YKMul9QpXJxKGH2X0wwqc9wYk4IoydzMaft",
                "nFI+q+ALNpU9BneMJ/FFhbP9NaHVBnQtzs9vNbsBPLEBTyZNfihuJtExRjDc",
                "Tj/tRvrOHsKWC+OdtMJT1MemgHw+/zXG59BwNOfxsStEV79O+F68g5I4Feom",
                "W9fPZYw6d/xLWqPXsxigPPUcH/JXGQ3+p1L+ChOiFvhCTMciJ3+7gc1huLWW",
                "ZqZtOTVxXWKMdia/ox31MqWxWiZcvHOJopum1RmR/OBwtaahSl27LmorLrr2",
                "QPRrtY+OVDBeeNQLNk9/aSzYcMWO66cju2C2Myvadfb6o8bTjw6rUJDSqlG+",
                "pvAhBhQvjxsrcPkT9BspFE+r/SPxWExKWL5djQYRit2druxBtw6Y+ylIg9CZ",
                "JMTf1IBjveBUySF+gOonShB1nLmRZ9zX2hwJSXqYbGQCqzfwRWwPYSnQak0s",
                "kh4J1p7OYFgZCuxkiSaEikpbuHJWAz1cpYsomXQ7l/m+F05JFfAm2LvNjVoe",
                "Ablw+Tj7T/Fx7x63b1CU4Wy3L7Ho25i89fksFhsV/fBk9c3QqSkwpkiDVnvY",
                "eIhM349An75ncfBjToQT5o5Ayn0ritOUHh5NW+SS+955CFFM8ZQhxZaluWKc",
                "rvjwN/UCtBWjGTu7JpwOnHuTTj03ti2wcYsEKNpPB/Nm3kql+UbARmsq24j5",
                "foxjN6gmiKVtuho7SDMsu11UGgidKCiHAM3/o4Im53awfRJiqoouJNTisSuh",
                "xHp7b+4Z14+CUPfQcKkyYCSzrptrJhg2FO5vz1YZOuExIm3deDfcPsK5vg4L",
                "iLam6FJ3qqonXP6krCuH/crJYLTPBJn6eX3noL7TjCqiMWEtLmGj0431Ybcr",
                "gG7Fy2a+VWwcB6w0nzyxbqg16AP+luuqHxfVsvP6Uyde4C7LPeB3r3GhAfuU",
                "Nxnpz/bXGxbJu3+aCnbtaZMzGJ6UFBeJp8MtlmVajDnjx3oEuOGGmobTlaop",
                "HYVsQ3ySfQ==",
            ]),
        ];
        $validKeysArgs = array_map(function ($x) {
            return [true, $x, "/^$/"];
        }, $validKeys);
        $garbageKeysArgs = array_map(function ($x) {
            return [false, $x, "/.*/"];
        }, $HTTP_HEADER_TEST_INPUTS);
        $invalidKeysArgs = [
            [false, "foo ", "/leading or trailing whitespace/"],
            [false, "foo\n", "/leading or trailing whitespace/"],
            [false, "foo\nbar", "/multiple lines/"],
            [false, "foo", "/at least 2 words/"],
            [false, "foo bar", "/key type 'foo' not allowed/"],
            [false, "ssh-rsa deadbeef", "/Unity admin/"],
        ];
        return $validKeysArgs + $garbageKeysArgs + $invalidKeysArgs;
    }

    #[DataProvider("SSHKeyProvider")]
    public function testTestValidSSHKey(bool $expected, string $key, string $explanation_regex)
    {
        [$is_valid, $explanation] = testValidSSHKey($key);
        $this->assertMatchesRegularExpression($explanation_regex, $explanation);
        $this->assertEquals($expected, $is_valid);
    }

    public static function sshKeyCommentProvider()
    {
        return [
            ["foo bar", "foo bar"],
            ["foo bar baz", "foo bar"],
            ["foo bar baz baz bam", "foo bar"],
        ];
    }

    #[DataProvider("sshKeyCommentProvider")]
    public function testRemoveSSHKeyOptionalCommentSuffix(string $input, string $expected_output)
    {
        $this->assertEquals(removeSSHKeyOptionalCommentSuffix($input), $expected_output);
    }

    public static function URLComponentProvider()
    {
        if (CONFIG["site"]["url"] != "http://127.0.0.1:8000/") {
            throw new RuntimeException("site url has changed!");
        }
        return [
            [["", ""], "http://127.0.0.1:8000/"],
            [["", "/"], "http://127.0.0.1:8000/"],
            [["/", "a"], "http://127.0.0.1:8000/a"],
            [["/", "/a"], "http://127.0.0.1:8000/a"],
            [["abc", "def"], "http://127.0.0.1:8000/abc/def"],
            [["abc", "/def"], "http://127.0.0.1:8000/abc/def"],
            [["/abc", "def"], "http://127.0.0.1:8000/abc/def"],
            [["/abc", "def///"], "http://127.0.0.1:8000/abc/def/"],
            [["", "foo.jpg"], "http://127.0.0.1:8000/foo.jpg"],
            [["dir", "0", "a.jpg"], "http://127.0.0.1:8000/dir/0/a.jpg"],
        ];
    }

    #[DataProvider("URLComponentProvider")]
    public function testGetURL(array $relative_url_components, string $expected)
    {
        $this->assertEquals($expected, getRelativeURL(...$relative_url_components));
    }

    public static function shortenStringProvider()
    {
        return [
            [["foo###bar", 3, 3, "..."], "foo...bar"],
            [["foo###bar", 1, 3, "..."], "f...bar"],
            // input string is shorter than requested output size
            [["foo###bar", 999, 999, "..."], "foo###bar"],
            // input string is shorter than requested output size due to the long ellipsis
            [["foo###bar", 3, 3, "...."], "foo###bar"],
            [["", 3, 3], ""],
        ];
    }

    #[DataProvider("shortenStringProvider")]
    public function testShortenString(array $input_args, string $expected_output)
    {
        $this->assertEquals(shortenString(...$input_args), $expected_output);
    }
}
