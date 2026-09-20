<?php
const DB_HOST='127.0.0.1'; const DB_NAME='credit_ads'; const DB_USER='root'; const DB_PASS='';
const BASE_URL='http://localhost/ad_module';
const VIEW_SECONDS=15; const VIEW_REWARD=1; const AD_PRICE_CENTS=100; const AD_CREDITS=500;
session_start();
function db():PDO{static $p; if(!$p)$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function csrf(){if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf(){if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??''))die('Invalid CSRF token');}
function user(){if(empty($_SESSION['uid']))return null;$s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$_SESSION['uid']]);return $s->fetch()?:null;}
function require_login(){if(!user()){header('Location: login.php');exit;}}
function require_admin(){ $u=user(); if(!$u||!$u['is_admin']){http_response_code(403);exit('Forbidden');}}
function valid_url($url){$p=parse_url($url);return $p&&in_array(strtolower($p['scheme']??''),['http','https'],true)&&!empty($p['host']);}
