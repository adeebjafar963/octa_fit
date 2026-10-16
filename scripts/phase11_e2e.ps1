$ErrorActionPreference = 'Continue'
$base = 'http://127.0.0.1:8000/api'
function J($method, $path, $body=$null, $token=$null) {
  $headers = @{ 'Accept'='application/json'; 'Content-Type'='application/json' }
  if ($token) { $headers.Authorization = "Bearer $token" }
  $params = @{ Uri = "$base$path"; Method = $method; Headers = $headers; TimeoutSec = 90 }
  if ($null -ne $body) { $params.Body = ($body | ConvertTo-Json -Depth 12 -Compress) }
  try {
    $resp = Invoke-WebRequest @params -UseBasicParsing
    return @{ ok=$true; status=[int]$resp.StatusCode; json=($resp.Content | ConvertFrom-Json) }
  } catch {
    $sc = $null; $content = $null
    if ($_.Exception.Response) {
      $sc = [int]$_.Exception.Response.StatusCode
      try {
        $stream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        $content = $reader.ReadToEnd()
      } catch {}
    }
    $parsed = $null
    if ($content) { try { $parsed = $content | ConvertFrom-Json } catch {} }
    return @{ ok=$false; status=$sc; json=$parsed; err=$_.Exception.Message; raw=$content }
  }
}

Write-Output '=== AUTH ==='
$bad = J POST '/login' @{ email='demo@octafit.test'; password='wrong' }
Write-Output "invalid_login status=$($bad.status) ok=$($bad.ok)"
$login = J POST '/login' @{ email='demo@octafit.test'; password='Demo123!' }
$token = $login.json.data.token
Write-Output "login status=$($login.status) token_len=$($token.Length)"
$me = J GET '/me' $null $token
Write-Output "me ok=$($me.ok) role=$($me.json.data.role.name)"

Write-Output '=== FEATURES ==='
$feat = J GET '/features' $null $token
Write-Output ($feat.json.data | ConvertTo-Json -Compress)

Write-Output '=== TRAINING ==='
$tr = J GET '/training/bodybuilding' $null $token
Write-Output "training ok=$($tr.ok) count=$($tr.json.data.items.Count)"
$exId = $tr.json.data.items[0].id
$ex = J GET "/training/exercises/$exId" $null $token
Write-Output "exercise ok=$($ex.ok)"

Write-Output '=== STORE ==='
$prods = J GET '/store/products' $null $token
$pid = $prods.json.data.items[0].id
$add = J POST '/store/cart/items' @{ product_id=$pid; quantity=1 } $token
Write-Output "add_cart status=$($add.status) ok=$($add.ok) msg=$($add.json.message)"
$cart = J GET '/store/cart' $null $token
Write-Output "cart ok=$($cart.ok)"
$order = J POST '/store/orders' @{
  shipping_name='Demo User'
  shipping_phone='0500000000'
  shipping_address='Test Address 1'
  payment_method='sham_cash'
} $token
Write-Output "checkout status=$($order.status) ok=$($order.ok) msg=$($order.json.message) raw=$($order.raw)"

Write-Output '=== COMMUNITY ==='
$post = J POST '/community/posts' @{ content='Phase11 temporary verification post' } $token
$postId = $post.json.data.id
Write-Output "post ok=$($post.ok) id=$postId"
$like = J POST "/community/posts/$postId/like" @{} $token
Write-Output "like ok=$($like.ok)"
$cmt = J POST "/community/posts/$postId/comments" @{ content='nice' } $token
Write-Output "comment ok=$($cmt.ok)"
# Do not leave E2E posts in the production feed
if ($postId) {
  $del = J DELETE "/community/posts/$postId" $null $token
  Write-Output "post_cleanup ok=$($del.ok)"
}
Write-Output '=== COACH ==='
$conv = J POST '/coach/conversations' @{ title='Phase11' } $token
$cid = $conv.json.data.id
Write-Output "conv ok=$($conv.ok) id=$cid"
$msg1 = J POST "/coach/conversations/$cid/messages" @{
  message='How do I build muscle as a beginner'
  language='en'
} $token
Write-Output "coach1 status=$($msg1.status) ok=$($msg1.ok) len=$($msg1.json.data.assistant_message.content.Length) msg=$($msg1.json.message)"
$msg2 = J POST "/coach/conversations/$cid/messages" @{
  message='What about protein intake'
  language='en'
} $token
Write-Output "coach2 ok=$($msg2.ok) len=$($msg2.json.data.assistant_message.content.Length)"
$msgAr = J POST "/coach/conversations/$cid/messages" @{
  message='How do I start strength training'
  language='ar'
} $token
Write-Output "coach_ar ok=$($msgAr.ok) len=$($msgAr.json.data.assistant_message.content.Length)"
$empty = J POST "/coach/conversations/$cid/messages" @{ message=''; language='en' } $token
Write-Output "coach_empty status=$($empty.status)"

Write-Output '=== PHYSIO ==='
$assessLow = J POST '/physio/assessments' @{
  language='en'
  body_area='knee'
  symptoms='Mild knee discomfort after running. No swelling.'
  pain_level=2
  duration='few days'
  onset='gradual'
  activity='running'
} $token
Write-Output "physio_low status=$($assessLow.status) ok=$($assessLow.ok) risk=$($assessLow.json.data.risk_level) msg=$($assessLow.json.message) raw=$($assessLow.raw)"

$assessRed = J POST '/physio/assessments' @{
  language='en'
  body_area='chest'
  symptoms='Sudden chest pain with numbness and shortness of breath'
  pain_level=9
  duration='hours'
  onset='sudden'
  activity='rest'
} $token
Write-Output "physio_red status=$($assessRed.status) ok=$($assessRed.ok) risk=$($assessRed.json.data.risk_level)"
if ($assessRed.json.data) {
  Write-Output ("physio_red_keys=" + (($assessRed.json.data | Get-Member -MemberType NoteProperty).Name -join ','))
}

Write-Output '=== MOTION ==='
$mex = J GET '/motion/exercises' $null $token
Write-Output "motion_ex ok=$($mex.ok) $($mex.json.data | ConvertTo-Json -Compress)"

Write-Output '=== ADMIN ==='
$adm = J GET '/admin/dashboard' $null $token
Write-Output "user_admin status=$($adm.status)"
$alogin = J POST '/login' @{ email='admin@octafit.test'; password='Admin123!' }
$at = $alogin.json.data.token
$dash = J GET '/admin/dashboard' $null $at
Write-Output "admin_dash ok=$($dash.ok)"
$off = J PUT '/admin/settings' @{ settings = @{ feature_coach_enabled = '0' } } $at
Write-Output "toggle_off ok=$($off.ok)"
$blocked = J GET '/coach/conversations' $null $token
Write-Output "coach_blocked status=$($blocked.status)"
$on = J PUT '/admin/settings' @{ settings = @{ feature_coach_enabled = '1' } } $at
Write-Output "toggle_on ok=$($on.ok)"
$unblocked = J GET '/coach/conversations' $null $token
Write-Output "coach_unblocked status=$($unblocked.status) ok=$($unblocked.ok)"

Write-Output '=== DONE ==='
