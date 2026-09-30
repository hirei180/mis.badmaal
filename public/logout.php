<?php
require dirname(__DIR__).'/app/web.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
requireValidCsrf();audit('logout');$_SESSION=[];$p=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>$p['secure'],'httponly'=>true,'samesite'=>'Lax']);session_destroy();header('Location: /login.php');
