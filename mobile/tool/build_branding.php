<?php
// Derive Android size variants from the original asset. Never alter the original.
$root=dirname(__DIR__);$source=imagecreatefromjpeg($root.'/assets/1.jpeg');
$sizes=['mdpi'=>48,'hdpi'=>72,'xhdpi'=>96,'xxhdpi'=>144,'xxxhdpi'=>192];
function variant($source,$size,$path){$out=imagecreatetruecolor($size,$size);imagefill($out,0,0,imagecolorallocate($out,255,255,255));$scale=min($size*.82/imagesx($source),$size*.82/imagesy($source));$w=(int)(imagesx($source)*$scale);$h=(int)(imagesy($source)*$scale);imagecopyresampled($out,$source,(int)(($size-$w)/2),(int)(($size-$h)/2),0,0,$w,$h,imagesx($source),imagesy($source));imagepng($out,$path);imagedestroy($out);}
foreach($sizes as $density=>$size)variant($source,$size,$root.'/android/app/src/main/res/mipmap-'.$density.'/ic_launcher.png');
variant($source,240,$root.'/android/app/src/main/res/drawable/school_splash.png');imagedestroy($source);
