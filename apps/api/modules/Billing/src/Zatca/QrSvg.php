<?php

namespace Modules\Billing\Zatca;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** A QR code as inline SVG (dark modules on a white square), drawn here: nothing leaves the server. */
final class QrSvg
{
    public static function render(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => false,
            'markupDark' => '#000',
            'cssClass' => 'zatca-qr',
        ]);

        return (new QRCode($options))->render($data);
    }
}
