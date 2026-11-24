<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$frontendOrigin = getenv('FRONTEND_ORIGIN') ?: 'http://localhost:5173';
header('Access-Control-Allow-Origin: ' . $frontendOrigin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['httponly'=>true,'secure'=>$isHttps,'samesite'=>$isHttps?'None':'Lax','path'=>'/']);
session_start();
$databaseUrl=getenv('DATABASE_URL');
$driver=$databaseUrl?'pgsql':'sqlite';
if($databaseUrl){
  $parts=parse_url($databaseUrl);
  if(!$parts||empty($parts['host'])||empty($parts['path']))throw new RuntimeException('DATABASE_URL is not a valid PostgreSQL connection URL.');
  parse_str($parts['query']??'', $queryParams);
  $dsn='pgsql:host='.$parts['host'].';port='.($parts['port']??5432).';dbname='.rawurldecode(ltrim($parts['path'],'/')).';sslmode='.($queryParams['sslmode']??'require');
  $db=new PDO($dsn,rawurldecode($parts['user']??''),rawurldecode($parts['pass']??''));
}else{
  $db=new PDO('sqlite:' . __DIR__ . '/data/marketplace.sqlite');
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if($driver==='sqlite')$db->exec('PRAGMA foreign_keys = ON');
$autoId=$driver==='pgsql'?'BIGSERIAL PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
$realType=$driver==='pgsql'?'DOUBLE PRECISION':'REAL';
$dateType=$driver==='pgsql'?'TIMESTAMPTZ':'TEXT';
$db->exec('CREATE TABLE IF NOT EXISTS products (id TEXT PRIMARY KEY, title TEXT NOT NULL, category TEXT NOT NULL, platform TEXT NOT NULL, region TEXT NOT NULL, image TEXT NOT NULL, description TEXT NOT NULL, rating '.$realType.' NOT NULL, badge TEXT)');
$db->exec('CREATE TABLE IF NOT EXISTS offers (id '.$autoId.', product_id TEXT NOT NULL REFERENCES products(id), seller TEXT NOT NULL, price '.$realType.' NOT NULL, stock INTEGER NOT NULL, rating '.$realType.' NOT NULL, delivery TEXT NOT NULL, key_code TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS users (id '.$autoId.', name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS orders (id '.$autoId.', user_id INTEGER NOT NULL REFERENCES users(id), offer_id INTEGER NOT NULL REFERENCES offers(id), created_at '.$dateType.' NOT NULL DEFAULT CURRENT_TIMESTAMP)');

if ((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
  $products = [
    ['elden-ring','Elden Ring','Games','PC','Global','https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=800&q=85','Rise, Tarnished. Explore the Lands Between in this award-winning open-world action RPG.',4.9,'BESTSELLER'],
    ['cyberpunk-2077','Cyberpunk 2077: Ultimate Edition','Games','PC','Global','https://images.unsplash.com/photo-1605899435973-ca2d1a8861cf?auto=format&fit=crop&w=800&q=85','Become a mercenary in the neon-soaked streets of Night City.',4.8,'HOT DEAL'],
    ['hogwarts-legacy','Hogwarts Legacy','Games','PlayStation 5','Europe','https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=800&q=85','Experience Hogwarts in the 1800s and decide the fate of the wizarding world.',4.7,''],
    ['gta-v','Grand Theft Auto V: Premium Edition','Games','PC','Global','https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=800&q=85','Experience the sprawling world of Los Santos and GTA Online.',4.8,'TOP PICK'],
    ['minecraft','Minecraft: Java & Bedrock Edition','Games','PC','Global','https://images.unsplash.com/photo-1587573089734-09cb69c0f2b9?auto=format&fit=crop&w=800&q=85','Build, explore and survive in a world limited only by your imagination.',4.9,''],
    ['fc-26','EA SPORTS FC 26','Games','Xbox Series X|S','Europe','https://images.unsplash.com/photo-1522778119026-d647f0596c20?auto=format&fit=crop&w=800&q=85','The club is yours. Build your dream squad and take to the pitch.',4.5,'NEW'],
    ['game-pass','Xbox Game Pass Ultimate 3 Months','Subscriptions','Xbox','Europe','https://images.unsplash.com/photo-1605901309584-818e25960a8f?auto=format&fit=crop&w=800&q=85','Discover your next favorite game with a library of hundreds of titles.',4.6,''],
    ['steam-card','Steam Gift Card 20 EUR','Gift cards','PC','Europe','https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?auto=format&fit=crop&w=800&q=85','Add funds to your Steam Wallet and choose from thousands of games.',4.8,''],
  ];
  $p=$db->prepare('INSERT INTO products VALUES (?,?,?,?,?,?,?,?,?)');
  foreach($products as $row) $p->execute($row);
  $offers=$db->prepare('INSERT INTO offers (product_id,seller,price,stock,rating,delivery,key_code) VALUES (?,?,?,?,?,?,?)');
  foreach($products as $i=>$row) foreach([['PixelVault', 14.99 + $i*1.7, 4.9],['GameHub',17.49 + $i*1.45,4.8],['DigitalDen',19.95 + $i*1.2,4.7]] as $j=>$o) $offers->execute([$row[0],$o[0],round($o[1],2),18-$j,$o[2],'Instant delivery','ENB-'.strtoupper(substr(sha1($row[0].$j),0,4)).'-'.strtoupper(substr(sha1('key'.$row[0].$j),0,4))]);
}
$db->exec("UPDATE products SET image='/minecraft-cover.svg' WHERE id='minecraft'");
function respond(mixed $body, int $status=200): never { http_response_code($status); echo json_encode($body, JSON_UNESCAPED_SLASHES); exit; }
function body(): array { return json_decode(file_get_contents('php://input') ?: '{}', true) ?? []; }
function user(): ?array { return $_SESSION['user'] ?? null; }
$method=$_SERVER['REQUEST_METHOD']; $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/api/health') respond(['status'=>'ok']);
if($path==='/api/me') respond(['user'=>user()]);
if($path==='/api/auth/register' && $method==='POST') {
  $b=body(); if(!filter_var($b['email']??'',FILTER_VALIDATE_EMAIL) || strlen($b['password']??'')<6) respond(['error'=>'Enter a valid email and a password of at least 6 characters.'],422);
  try {$s=$db->prepare($driver==='pgsql'?'INSERT INTO users(name,email,password) VALUES(?,?,?) RETURNING id':'INSERT INTO users(name,email,password) VALUES(?,?,?)');$s->execute([trim($b['name']??'Player'),strtolower($b['email']),password_hash($b['password'],PASSWORD_DEFAULT)]);$id=$driver==='pgsql'?(int)$s->fetchColumn():(int)$db->lastInsertId();} catch(Throwable) {respond(['error'=>'An account with that email already exists.'],409);}
  $_SESSION['user']=['id'=>$id,'name'=>trim($b['name']??'Player'),'email'=>strtolower($b['email'])]; respond(['user'=>user()],201);
}
if($path==='/api/auth/login' && $method==='POST') {$b=body();$s=$db->prepare('SELECT * FROM users WHERE email=?');$s->execute([strtolower($b['email']??'')]);$u=$s->fetch(PDO::FETCH_ASSOC);if(!$u||!password_verify($b['password']??'',$u['password']))respond(['error'=>'Email or password is incorrect.'],401);$_SESSION['user']=['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email']];respond(['user'=>user()]);}
if($path==='/api/auth/logout'&&$method==='POST'){session_destroy();respond(['ok'=>true]);}
if($path==='/api/homepage') {
  // Each block is its own service-level query so homepage content is data driven.
  $blocks=[]; foreach(['deals'=>['Hot deals','YOUR NEXT BIG SAVE',"o.price < 22"],'bestSellers'=>['Best sellers','PLAYER FAVORITES',"p.badge = 'BESTSELLER' OR p.badge = 'TOP PICK'"],'giftCards'=>['Gift cards','GIVE THE GIFT OF PLAY',"p.category = 'Gift cards'"]] as $name=>$module){$q=$db->query("SELECT p.*, MIN(o.price) AS price, MIN(o.id) AS offerId FROM products p JOIN offers o ON o.product_id=p.id WHERE {$module[2]} GROUP BY p.id ORDER BY price LIMIT 6");$blocks[$name]=['title'=>$module[0],'eyebrow'=>$module[1],'products'=>$q->fetchAll(PDO::FETCH_ASSOC)];}
  $q=$db->query("SELECT p.*, MIN(o.price) AS price FROM products p JOIN offers o ON o.product_id=p.id WHERE p.id='elden-ring' GROUP BY p.id");$hero=$q->fetch(PDO::FETCH_ASSOC);
  respond(['banner'=>['eyebrow'=>'LEVEL UP YOUR PLAY','headline'=>'More game.','highlight'=>'Less spend.','description'=>'Big adventures, legendary deals. Find your next favorite game for less.','cta'=>'Explore games','product'=>$hero],'blocks'=>$blocks]);
}
if($path==='/api/products') {
  $q=trim($_GET['q']??'');$platform=$_GET['platform']??'';$region=$_GET['region']??'';$max=$_GET['max']??'';$sort=$_GET['sort']??'popular';
  $rows=$db->query('SELECT p.*, MIN(o.price) AS price, MIN(o.id) AS offerId FROM products p JOIN offers o ON o.product_id=p.id GROUP BY p.id')->fetchAll(PDO::FETCH_ASSOC);
  if($q!==''){$needle=mb_strtolower($q);$queryWords=preg_split('/[^\p{L}\p{N}]+/u',$needle,-1,PREG_SPLIT_NO_EMPTY);$rows=array_values(array_filter($rows,function($r)use($needle,$queryWords){$title=mb_strtolower($r['title']);if(str_contains($title,$needle)||str_contains($needle,$title))return true;$titleWords=preg_split('/[^\p{L}\p{N}]+/u',$title,-1,PREG_SPLIT_NO_EMPTY);foreach($queryWords as $word){$found=false;foreach($titleWords as $titleWord){if(str_contains($titleWord,$word)||levenshtein(substr($word,0,80),substr($titleWord,0,80))<=max(1,(int)floor(mb_strlen($word)*0.25))){$found=true;break;}}if(!$found)return false;}return count($queryWords)>0;}));}
  if($platform!=='')$rows=array_values(array_filter($rows,fn($r)=>$r['platform']===$platform)); if($region!=='')$rows=array_values(array_filter($rows,fn($r)=>$r['region']===$region)); if($max!=='')$rows=array_values(array_filter($rows,fn($r)=>(float)$r['price']<=(float)$max));
  usort($rows,fn($a,$b)=>$sort==='price_asc'?$a['price']<=>$b['price']:($sort==='price_desc'?$b['price']<=>$a['price']:(($b['rating']<=>$a['rating'])?:strcmp($a['title'],$b['title'])))); respond($rows);
}
if(preg_match('#^/api/products/([^/]+)$#',$path,$m)) {$s=$db->prepare('SELECT * FROM products WHERE id=?');$s->execute([$m[1]]);$p=$s->fetch(PDO::FETCH_ASSOC);if(!$p)respond(['error'=>'Product not found'],404);$s=$db->prepare('SELECT id,seller,price,stock,rating,delivery FROM offers WHERE product_id=? ORDER BY price');$s->execute([$m[1]]);respond(['product'=>$p,'offers'=>$s->fetchAll(PDO::FETCH_ASSOC)]);}
if($path==='/api/orders'&&$method==='GET'){if(!user())respond(['error'=>'Sign in required'],401);$s=$db->prepare('SELECT o.id,o.created_at,p.title,p.image,ofr.price,ofr.key_code FROM orders o JOIN offers ofr ON ofr.id=o.offer_id JOIN products p ON p.id=ofr.product_id WHERE o.user_id=? ORDER BY o.id DESC');$s->execute([user()['id']]);respond($s->fetchAll(PDO::FETCH_ASSOC));}
if($path==='/api/checkout'&&$method==='POST'){if(!user())respond(['error'=>'Sign in to complete checkout.'],401);$ids=body()['offers']??[];if(!is_array($ids)||count($ids)===0)respond(['error'=>'Your cart is empty.'],422);$db->beginTransaction();$orders=[];foreach($ids as $id){$s=$db->prepare('SELECT o.id,o.price,o.key_code,p.title FROM offers o JOIN products p ON p.id=o.product_id WHERE o.id=?');$s->execute([$id]);$offer=$s->fetch(PDO::FETCH_ASSOC);if(!$offer){$db->rollBack();respond(['error'=>'One of the selected offers is unavailable.'],422);}$ins=$db->prepare('INSERT INTO orders(user_id,offer_id) VALUES(?,?)');$ins->execute([user()['id'],$id]);$orders[]=['title'=>$offer['title'],'price'=>$offer['price'],'key'=>$offer['key_code']];}$db->commit();respond(['orders'=>$orders,'simulated'=>true],201);}
respond(['error'=>'Not found'],404);
