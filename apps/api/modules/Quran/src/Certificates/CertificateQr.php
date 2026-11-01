<?php

namespace Modules\Quran\Certificates;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** The certificate's QR code to its verification page, as inline SVG drawn here: nothing leaves the server. */
final class CertificateQr
{
    public static function svg(string $url): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => false,
            'markupDark' => '#000',
            'cssClass' => 'cert-qr',
        ])))->render($url);
    }
}
