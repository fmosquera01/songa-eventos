<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();

function ejecutarIpconfig(): string { return shell_exec('ipconfig /all 2>&1') ?? ''; }
function detectarInterfaces(string $salida): array {
    $interfaces=[];
    $bloques=preg_split('/(?=^\s*(?:Adaptador|Adapter)\s+)/mi',$salida);
    foreach($bloques as $bloque){
        if(trim($bloque)==='')continue;
        $nombre='';
        if(preg_match('/^\s*(?:Adaptador|Adapter)\s+(.*?):\s*$/mi',$bloque,$m))$nombre=trim($m[1]);
        $ip=null;if(preg_match('/IPv4[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',$bloque,$m))$ip=$m[1];
        $mascara=null;if(preg_match('/(?:Máscara de subred|Subnet Mask)[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',$bloque,$m))$mascara=$m[1];
        $gateway=null;if(preg_match('/(?:Puerta de enlace predeterminada|Default Gateway)[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',$bloque,$m))$gateway=$m[1];
        $wifi=preg_match('/wi[\s-]?fi|wifi|inal[aá]mbrica|wireless/i',$nombre.' '.$bloque);
        $interfaces[]=['nombre'=>$nombre,'ip'=>$ip,'mascara'=>$mascara,'gateway'=>$gateway,'wifi'=>(bool)$wifi,'activo'=>$ip!==null];
    }
    return $interfaces;
}
$interfaces=detectarInterfaces(ejecutarIpconfig());$wifi=null;
foreach($interfaces as $interfaz){if($interfaz['wifi']&&$interfaz['activo']&&filter_var($interfaz['ip'],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){$wifi=$interfaz;break;}}
$urlMovil=$wifi?'https://'.$wifi['ip'].'/movil':'';
?>
<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Red - Songa Eventos</title><style>*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f6f8;color:#1f2937;padding:30px}.contenedor{max-width:1050px;margin:auto}.volver{display:inline-block;margin-bottom:18px;color:#2563eb;text-decoration:none;font-weight:bold}.card{background:#fff;border-radius:14px;padding:28px;box-shadow:0 3px 15px rgba(0,0,0,.08)}h1{margin:0 0 22px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:35px;align-items:center}.ok{color:#198754;font-size:20px;font-weight:bold}.dato{margin:16px 0;font-size:17px}.ip{font-size:36px;font-weight:bold;color:#1769d1;margin:8px 0 24px}.url{background:#eef5ff;padding:16px;border-radius:9px;font-size:19px;word-break:break-all;color:#1769d1;font-weight:bold}.qr{text-align:center}.qrbox{display:inline-flex;background:#fff;padding:20px;border:1px solid #dbe3ec;border-radius:12px;min-width:300px;min-height:300px;align-items:center;justify-content:center}.qrbox img{display:block}.error{color:#dc3545;font-weight:bold;font-size:18px}@media(max-width:750px){body{padding:15px}.grid{grid-template-columns:1fr}.qrbox{min-width:270px;min-height:270px}.qrbox img{max-width:250px!important;height:auto!important}}</style><script src="/js/qrcode.min.js"></script></head><body><div class="contenedor"><a href="eventos.php" class="volver">← Volver a eventos</a><div class="card"><h1>🌐 Red Songa Eventos</h1><?php if($wifi):?><div class="grid"><div><p class="ok">✓ Interfaz Wi-Fi detectada</p><div class="dato"><strong>Adaptador:</strong> <?=htmlspecialchars($wifi['nombre'])?></div><div class="dato"><strong>IP:</strong></div><div class="ip"><?=htmlspecialchars($wifi['ip'])?></div><h3>📱 Acceso desde PDA / celular</h3><div class="url"><?=htmlspecialchars($urlMovil)?></div><p>Conecta el PDA o celular a la misma red Wi-Fi y escanea el código QR.</p></div><div class="qr"><h2>📷 Escanea para acceder</h2><div id="qrcode" class="qrbox"></div><p>El código abrirá directamente el sitio móvil de Songa Eventos.</p></div></div><?php else:?><p class="error">✗ No se encontró una interfaz Wi-Fi activa.</p><p>Verifica que el equipo esté conectado al Wi-Fi del TCL LINKZONE.</p><?php endif;?></div></div><?php if($wifi):?><script>new QRCode(document.getElementById('qrcode'),{text:<?=json_encode($urlMovil,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,width:280,height:280,colorDark:'#000000',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});</script><?php endif;?></body></html>
