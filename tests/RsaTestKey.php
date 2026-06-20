<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests;

/**
 * A fixed RSA test key + precomputed signatures.
 *
 * Generated once with `openssl genrsa 2048`. Using a static keypair (rather
 * than openssl_pkey_new() at runtime) keeps the RSA verification tests
 * deterministic and runnable even on PHP setups where key generation is
 * unavailable. The signatures below are RSA-SHA256 over the exact bodies noted.
 */
class RsaTestKey
{
    /** base64 RSA-SHA256 signature of the body {"event":"paid"} */
    public const SIG_EVENT_PAID = 'BUwCuqWzAoivVmah5lWz38mjvbvbmZAo4T285JMKXP5mr4bydh32IS+H14NuCJtCbki+lDyJIH3xNx+UUM8Nx8yYs41thihih/+qSMviLN+gSTXkDD4QBC5gy7nLB6jZ3u96m1uBZfioYkbxCwshbMKuTp4sSxiKDzshfV5TAq/T4A8wvHxlsH8QnkfA1pkjV+RUu+rlTY08fZuoUaAmaFllHTZs8v9u+ImtpDO+jjAVP9yutEXyECTh5ueGZShWVaeaNushCmcu5QMkPAlFbc9FSG9FMugxYigQ1HQC882N8ye0+DXP2+k8cLpKt9PTcxcTuMOCIaol/5H9SxkqzA==';

    /** base64 RSA-SHA256 signature of the body {"status":"paid","id":"p1"} */
    public const SIG_CHIP_PAID = 'BBDyO2Paqx9zdqVv2V8EcouBIEmtqtHzIHdMInzvJT8BzeGD0rvfKugzR0wYqGAyXtckoV4Errd9pjBov72Cv4P/Sst/TDKWghU5lqrtcSUhRoNfR92ohjIMG7fp5NX0sNpLmL5XPuFbM6R5PA1Ci6Q//y1wK1E/hrRSGTk16RkNDk4xxn7HYXBXPjJ5sf+zcYiolkjceOYbIxPLyDxnpTc7gy3UZx6YGZQre+Pvnw/Bv12/qt8u+ep9aK6kqf/DAjMJxTVjwsqDFWA4hWJtY/ruz5v3mWupGlZBP9B1hh0N8o2p2XRaJGUUkxP/UoatVwKu2SUkl3KcuE0Dp0sdjg==';

    public static function publicPem(): string
    {
        return <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA46DnQ6KMp42vysqTrtlO
FrAMO/GWsUrh8V23NB6fYOVlDy5NITDfGcF/Iyu6Mc3VK9WV0H00O4w0j4gF3v5T
1VdAfcQa6skRZSeBsIH0iDc2TwL4uNFchao3BaI8V4O4ZD3fwj0cinNmFgUb+qEk
BG05d3e5oR3ylzbeajm6qh8HVt8vvVBf7e4wRAgRxT/b9vDANSuTEXTOqU7I85lj
82eoynh2jY3QCOsVodxTAsaaCIdsonpr2gIRjm/OKPRkyHiKzAlT2YZFr8zPtaxy
3hYkmb7qgleM+29TKAGQR8o0vfOZfziXxkUrOQhUnq4jyMusm0QoHlH8uSgJaV3x
IwIDAQAB
-----END PUBLIC KEY-----
PEM;
    }
}
