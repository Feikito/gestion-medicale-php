<?php
function get_email_html_layout(string $titreEmail, string $contenuPrincipalHtml, string $nomPlateforme = "SANTE TV"): string {
    $anneeCourante = date('Y');
    $logoUrl = ""; // Laissez vide si pas de logo, ou mettez le chemin absolu web vers votre logo
                   // ex: $logoUrl = "https://votresite.com/assets/images/logo_email.png";

    $headerHtml = $nomPlateforme;
    if (!empty($logoUrl)) {
        $headerHtml = "<img src='{$logoUrl}' alt='{$nomPlateforme} Logo' style='max-height:50px;display:block;margin:0 auto 15px auto;'>";
    } else {
        $headerHtml = "<h1 style='font-size: 24px; margin: 0; color: white; font-weight: 600;'>" . htmlspecialchars($nomPlateforme) . "</h1>";
    }


    return <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$titreEmail}</title>
        <style>
            body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; background-color: #f4f6f9; font-family: Arial, sans-serif; }
            table { border-spacing: 0; }
            td { padding: 0; }
            img { border: 0; max-width: 100%; height: auto; }
            .wrapper { width: 100%; table-layout: fixed; background-color: #f4f6f9; padding-top: 40px; padding-bottom: 60px; }
            .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 600px; border-spacing: 0; font-family: Arial, sans-serif; color: #333333; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow:hidden; }
            .header { padding: 25px 30px; text-align: center; background-color: #0A77A8; color: white; }
            .content { padding: 30px; font-size: 15px; line-height: 1.7; color: #333333;}
            .content p { margin: 0 0 18px 0; }
            .content strong { font-weight: bold; }
            .content a { color: #0A77A8; text-decoration: underline; }
            .button-container { text-align: center; margin: 25px 0; }
            .button { background-color: #0A77A8; color: #ffffff !important; text-decoration: none !important; padding: 12px 25px; border-radius: 5px; display: inline-block; font-weight: bold; font-size: 16px; }
            .footer { background-color: #f0f4f7; padding: 20px 30px; text-align: center; font-size: 12px; color: #7f8c8d;}
            .footer p { margin: 0 0 5px 0; }
        </style>
    </head>
    <body>
        <center class="wrapper">
            <table class="main" width="100%">
                <tr>
                    <td class="header">
                        {$headerHtml}
                    </td>
                </tr>
                <tr>
                    <td class="content">
                        {$contenuPrincipalHtml}
                    </td>
                </tr>
                <tr>
                    <td class="footer">
                        <p>© {$anneeCourante} {$nomPlateforme}. Tous droits réservés.</p>
                        <p><small>Ceci est un message automatisé. Pour des raisons de sécurité, ne répondez pas directement à cet e-mail si vous n'êtes pas sûr de son origine. Contactez-nous via notre site officiel si besoin.</small></p>
                    </td>
                </tr>
            </table>
        </center>
    </body>
    </html>
HTML;
}
?>