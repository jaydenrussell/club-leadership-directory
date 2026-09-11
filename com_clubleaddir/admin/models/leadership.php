<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
require_once __DIR__ . '/../store/Store.php';
class ClubleaddirModelLeadership extends BaseDatabaseModel
{
    private $store; public $item;
    public function __construct($config=[]){ parent::__construct($config); try{ $this->store=ClubleaddirStore::getInstance(); }catch(\Throwable $e){ $this->store=null; } }
    public function getItem($pk=null){
        if($this->store===null) return (object)[];
        $id=(int)$pk; if($id){ $row=$this->store->getById($id); if($row){ $this->item=$row; return $row; } }
        $this->item=(object)['id'=>0,'name'=>'','type'=>'director','role'=>'','league_name'=>'','term'=>'','start_year'=>0,'end_year'=>0,'bio'=>'','photo'=>'','photo_full'=>'','email'=>'','phone'=>'','contact_id'=>0,'vacant'=>0,'ordering'=>0,'published'=>1,'status'=>'active','created'=>'','modified'=>'','created_by'=>0,'modified_by'=>0];
        return $this->item;
    }
    public function save(array $data){
        $date=Factory::getDate()->toSql(); $userId=(int)Factory::getUser()->id; $data=$this->validate($data); if($data===false) return false;
        // Vacant: name is logical "Vacant", role is the unique identifier (keeps admin access)
        if (!empty($data['vacant']) && trim((string)($data['name'] ?? '')) === '') {
            $data['name'] = 'Vacant';
        }
        // Hard caps — cheap hosting, maintainer copy-paste long bio
        $data['name']=mb_substr(trim((string)($data['name']??'')),0,120);
        $data['role']=mb_substr(trim((string)($data['role']??'')),0,80);
        $data['league_name']=mb_substr(trim((string)($data['league_name']??'')),0,40);
        $data['term']=mb_substr(trim((string)($data['term']??'')),0,9);
        $data['bio']=mb_substr((string)($data['bio']??''),0,5000);
        $data['email']=mb_substr(trim((string)($data['email']??'')),0,254);
        $data['phone']=preg_replace('/[^0-9+\-\s\(\)]/','', (string)($data['phone']??'')); $data['phone']=mb_substr($data['phone'],0,30);
        $data['ordering']=max(0,min(9999,(int)($data['ordering']??0)));
        $data['published']=in_array((int)($data['published']??1),[1,0,-2],true)?(int)$data['published']:1;
        $data['status']=($data['status']??'active')==='archived'?'archived':'active';
        $data['contact_id']=max(0,(int)($data['contact_id']??0));
        $record=['name'=>$data['name'],'type'=>$data['type'],'role'=>$data['role']??'','league_name'=>$data['league_name']??'','term'=>$data['term']??'','bio'=>$data['bio']??'','email'=>$data['email']??'','phone'=>$data['phone']??'','contact_id'=>(int)($data['contact_id']??0),'vacant'=>!empty($data['vacant'])?1:0,'ordering'=>(int)($data['ordering']??0),'published'=>isset($data['published'])?(int)$data['published']:1,'status'=>$data['status']??'active'];
        $app=Factory::getApplication(); $files=$app->input->files->get('jform',[],'array');
        $existing=null; if(!empty($data['id'])) $existing=$this->store->getById((int)$data['id']);
        if(!empty($files['photo']['name'])){
            // Legacy single-file upload (kept for old clients / batch scripts).
            if(($files['photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
            if(!is_uploaded_file($files['photo']['tmp_name'])){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
            $photoPaths=$this->handlePhotoUpload($files['photo']); if($photoPaths===false) return false;
            $record['photo_full']=$photoPaths[0]; $record['photo']=$photoPaths[1];
        }elseif(trim((string)($data['photo'] ?? '')) !== ''){
            // Media picker: reuse an image already stored under images/clubleaddir/photos
            // (the picker's default folder), generating a square crop only on first use.
            $photoPaths=$this->setPhotoFromPicker((string)$data['photo'], $existing);
            if($photoPaths===false) return false;
            $record['photo_full']=$photoPaths[0]; $record['photo']=$photoPaths[1];
        }elseif(array_key_exists('photo',$data) && $existing){
            // Media picker posted an empty value: the admin explicitly cleared the photo.
            $record['photo']=''; $record['photo_full']='';
        }elseif($existing){
            // No photo field in the payload (batch/programmatic saves): leave untouched.
            $record['photo']=$existing->photo; $record['photo_full']=$existing->photo_full;
        }
        $record['modified']=$date; $record['modified_by']=$userId;
        $orderingChanged = false;
        if(!empty($data['id'])){
            $existing=$this->store->getById((int)$data['id']);
            if($existing){
                $record['created']=$existing->created; $record['created_by']=$existing->created_by;
                $orderingChanged = ((int)($record['ordering'] ?? 0) !== (int)($existing->ordering ?? 0));
            }
            $result=(bool)$this->store->update((int)$data['id'],$record);
            if($result) $this->logAudit('update', (int)$data['id'], array('id' => (int)$data['id']));
        }else{
            $record['created']=$date; $record['created_by']=$userId;
            $orderingChanged = ((int)($record['ordering'] ?? 0) === 0);
            $result=(bool)$this->store->insert($record);
            if($result) $this->logAudit('insert', (int)($record['id'] ?? 0), array('record' => $record));
        }
        if($result && $this->store!==null && $orderingChanged) $this->store->reorderAll($record['type']??null);
        return $result;
    }
    private function validate(array $data){
        $vacant=!empty($data['vacant']);
        if(!$vacant && mb_strlen(trim($data['name']??''))===0){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_NAME_REQUIRED')); return false; }
        if(mb_strlen(trim($data['name']??''))>120){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_NAME_REQUIRED')); return false; }
        if(empty(trim($data['type']??''))){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_TYPE_REQUIRED')); return false; }
        $valid=['officer','director','director_league','staff']; if(!in_array($data['type'],$valid,true)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_INVALID_TYPE')); return false; }
        if($data['type']==='director_league' && empty(trim($data['league_name']??''))){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_LEAGUE_REQUIRED')); return false; }
        if(!empty(trim($data['email']??'')) && !filter_var(trim($data['email']),FILTER_VALIDATE_EMAIL)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_EMAIL_INVALID')); return false; }
        if($data['type']==='officer'){
            $allowed=['President','Vice President','Secretary','Treasurer']; $role=trim($data['role']??'');
            if($role==='' || !in_array($role,$allowed,true)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_OFFICER_ROLE_INVALID')); return false; }
        }
        if(isset($data['term']) && mb_strlen($data['term'])>9){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_INVALID_TYPE')); return false; }
        if(isset($data['bio']) && mb_strlen($data['bio'])>5000){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_SAVING')); return false; }
        return $data;
    }
    protected function handlePhotoUpload($fileInfo){
        $allowedMimes=['image/jpeg','image/png','image/gif','image/webp']; $maxSize=2*1024*1024;
        if(($fileInfo['size']??0) > $maxSize){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_TOO_LARGE')); return false; }
        if(!is_file($fileInfo['tmp_name'])){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
        $dims=getimagesize($fileInfo['tmp_name']); if($dims && ($dims[0]>2500 || $dims[1]>2500)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_DIMENSIONS')); return false; }
        if($dims && ($dims[0]*$dims[1] > 6250000)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_DIMENSIONS')); return false; }
        $mime=null; if(class_exists('finfo')){ try{$f=new \finfo(FILEINFO_MIME_TYPE); $mime=$f->file($fileInfo['tmp_name']);}catch(\Throwable $e){ $mime=null; } }
        if(!$mime) $mime=mime_content_type($fileInfo['tmp_name']);
        if(!in_array($mime,$allowedMimes,true)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_INVALID_TYPE')); return false; }
        $ext='jpg'; switch($mime){ case 'image/png': $ext='png'; break; case 'image/gif': $ext='gif'; break; case 'image/webp': $ext='webp'; break; }
        $destDir=JPATH_ROOT.'/images/clubleaddir/photos';
        if(!is_dir($destDir)){
            $ok = @mkdir($destDir,0755,true);
            if(!$ok && !is_dir($destDir)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
        }
        do {
            try { $base='photo_'.time().'_'.bin2hex(random_bytes(4)); }
            catch (\Throwable $e) { $base='photo_'.time().'_'.bin2hex(openssl_random_pseudo_bytes(4)); }
            $orig=$base.'.'.$ext; $square=$base.'_sq.'.$ext; $origPath=$destDir.'/'.$orig; $squarePath=$destDir.'/'.$square;
        } while (is_file($origPath) || is_file($squarePath));
        if(!move_uploaded_file($fileInfo['tmp_name'],$origPath)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
        @chmod($origPath,0644);
        $this->makeSquareCrop($origPath,$squarePath,400);
        if(is_file($squarePath)){ @chmod($squarePath,0644); }
        return ['/images/clubleaddir/photos/'.$orig,'/images/clubleaddir/photos/'.$square];
    }
    /**
     * Normalise a media-picker value to the canonical store path
     * (/images/clubleaddir/photos/<name>) or null when it does not resolve to
     * an allowed file inside the photos directory. Accepts every form the
     * Joomla media field returns across versions: leading-slash absolute,
     * site-root relative, images/-relative, folder-relative, and the J4
     * media://local/ scheme. Any other location or a traversal attempt is
     * rejected.
     */
    private function normalizePhotoPath($path)
    {
        $p = trim((string) $path);
        if ($p === '' || preg_match('#^https?://#i', $p)) {
            return null;
        }
        if (strpos($p, 'media://local/') === 0) {
            $p = substr($p, strlen('media://local/'));
        }
        if (strpos($p, '/images/clubleaddir/photos/') === 0) {
            $rel = substr($p, strlen('/images/clubleaddir/photos/'));
        } elseif (strpos($p, 'images/clubleaddir/photos/') === 0) {
            $rel = substr($p, strlen('images/clubleaddir/photos/'));
        } elseif (strpos($p, 'clubleaddir/photos/') === 0) {
            $rel = substr($p, strlen('clubleaddir/photos/'));
        } else {
            return null;
        }
        $rel = str_replace('\\', '/', $rel);
        if ($rel === '' || strpos($rel, '/') !== false || strpos($rel, '..') !== false) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/D', $rel)) {
            return null;
        }
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/D', strtolower($rel))) {
            return null;
        }
        return '/images/clubleaddir/photos/' . $rel;
    }
    /**
     * Persist an image chosen through the media picker. The file must already
     * exist under images/clubleaddir/photos; a square avatar crop is generated
     * next to it on first use and reused afterwards, mirroring the upload
     * flow's photo/photo_full pair. Returns array($photoFull,$photo) or false.
     */
    protected function setPhotoFromPicker($path, $existing)
    {
        $canonical = $this->normalizePhotoPath($path);
        if ($canonical === null) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_INVALID_TYPE'));
            return false;
        }
        $abs = JPATH_ROOT . $canonical;

        if ($existing) {
            $exFull = (string) ($existing->photo_full ?? '');
            $exSq   = (string) ($existing->photo ?? '');
            if ($exFull === $canonical && $exSq !== ''
                && strpos($exSq, '/images/clubleaddir/photos/') === 0
                && is_file(JPATH_ROOT . $exSq)) {
                return array($exFull, $exSq);
            }
        }

        if (!is_file($abs) || !is_readable($abs)) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED'));
            return false;
        }
        $fs = @filesize($abs);
        if ($fs === false || $fs <= 0 || $fs > 6291456) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_TOO_LARGE'));
            return false;
        }
        $dims = @getimagesize($abs);
        if (!$dims || !isset(array('image/jpeg'=>1,'image/png'=>1,'image/gif'=>1,'image/webp'=>1)[$dims['mime'] ?? ''])) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_INVALID_TYPE'));
            return false;
        }
        if ($dims[0] > 2500 || $dims[1] > 2500 || ($dims[0] * $dims[1]) > 6250000) {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_DIMENSIONS'));
            return false;
        }

        $dir  = dirname($abs);
        $base = pathinfo($canonical, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($canonical, PATHINFO_EXTENSION));

        // The picker may have selected the pre-generated square itself.
        if (preg_match('/_sq$/D', $base)) {
            $origBase = substr($base, 0, -3);
            if (!is_file($dir . '/' . $origBase . '.' . $ext)) {
                // Degenerate: only the square exists; keep it as-is.
                return array($canonical, $canonical);
            }
            return array('/images/clubleaddir/photos/' . $origBase . '.' . $ext, $canonical);
        }

        $sqPath = $dir . '/' . $base . '_sq.' . $ext;
        $sq = '';
        if (is_file($sqPath)) {
            $sq = '/images/clubleaddir/photos/' . basename($sqPath);
        } elseif ($this->makeSquareCrop($abs, $sqPath, 400)) {
            if (is_file($sqPath)) {
                chmod($sqPath, 0644);
                $sq = '/images/clubleaddir/photos/' . basename($sqPath);
            }
        }
        // GD absent or crop failed: keep the original as both files rather than
        // fail the save; the avatar still renders uncompressed.
        return array($canonical, $sq !== '' ? $sq : $canonical);
    }
    protected function makeSquareCrop($src,$dest,$size=400){
        if(!function_exists('imagecreatefromstring')) return false;
        gc_collect_cycles();
        $dims=getimagesize($src);
        if($dims && ($dims[0]>2500 || $dims[1]>2500)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_DIMENSIONS')); return false; }
        if($dims && ($dims[0]*$dims[1] > 6250000)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_DIMENSIONS')); return false; }
        $memLimit = $this->memoryLimitBytes();
        $estimated = ($dims[0] ?? 0) * ($dims[1] ?? 0) * 4.5;
        if ($estimated > 0 && $memLimit > 0 && $estimated > $memLimit * 0.75) {
            return false;
        }
        $img=imagecreatefromstring(file_get_contents($src)); if($img===false) return false;
        $sw=imagesx($img); $sh=imagesy($img); if(!$sw||!$sh){ imagedestroy($img); return false; }
        $side=min($sw,$sh); $srcX=(int)(($sw-$side)/2); $srcY=(int)(($sh-$side)*0.38); if($srcY<0) $srcY=0;
        $out=imagecreatetruecolor($size,$size); if(!$out){ imagedestroy($img); return false; }
        imagefill($out,0,0,imagecolorallocate($out,255,255,255)); imagesavealpha($out,true); imagealphablending($out,false);
        imagecopyresampled($out,$img,0,0,$srcX,$srcY,$size,$size,$side,$side);
        $ok=false; $low=strtolower($dest);
        if (substr($low,-5)==='.webp' && function_exists('imagewebp')) {
            $ok=imagewebp($out,$dest,90);
        } elseif (substr($low,-5)==='.webp' && !function_exists('imagewebp')) {
            $jpgDest = preg_replace('/\.webp$/i', '.jpg', $dest);
            $ok=imagejpeg($out,$jpgDest,90);
        } elseif (substr($low,-4)==='.png') {
            $ok=imagepng($out,$dest,8);
        } elseif (substr($low,-4)==='.gif') {
            $ok=imagegif($out,$dest);
        } elseif (substr($low,-5)==='.webp') {
            $ok=imagewebp($out,$dest,90);
        } else {
            $ok=imagejpeg($out,$dest,90);
        }
        imagedestroy($img); imagedestroy($out); return (bool)$ok;
    }
    public function delete(array $pks){
        $user = Factory::getUser();
        $ok = true;
        foreach($pks as $pk) {
            $id = (int)$pk;
            if(!$this->store->delete($id)) {
                $ok = false;
            } else {
                $this->logAudit('delete', $id, array('id' => $id));
            }
        }
        return $ok;
    }
    public function publish(array $pks, $state=1){
        $user = Factory::getUser();
        $ok = true;
        foreach($pks as $pk) {
            $id = (int)$pk;
            if(!$this->store->setPublished($id, (int)$state)) {
                $ok = false;
            } else {
                $this->logAudit('publish', $id, array('id' => $id, 'state' => (int)$state));
            }
        }
        return $ok;
    }
    public function trash(array $pks){ return $this->publish($pks, -2); }
    public function reorderSingle($id, $dir){
        $result = $this->store->reorderSingle((int)$id, (int)$dir);
        if ($result) {
            $this->logAudit('reorder', (int)$id, array('id' => (int)$id, 'direction' => (int)$dir));
        }
        return $result;
    }
    public function saveOrder(array $pks, array $order){
        if($this->store===null) return false;
        $user = Factory::getUser();
        $ok = false;
        try {
            $ok = (bool)$this->store->saveOrderAll($pks, $order);
        } catch (\Throwable $e) {
            Log::add('Clubleaddir saveOrder failed: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_SAVING'));
        }
        if ($ok) {
            $this->logAudit('saveOrder', 0, array('pks' => $pks, 'order' => $order));
        } else {
            $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_SAVING'));
        }
        return $ok;
    }
    private function logAudit($action, $id, array $data) {
        try {
            $user = Factory::getUser();
            $logDir = JPATH_ADMINISTRATOR . '/components/com_clubleaddir/logs';
            if (!is_dir($logDir) && !mkdir($logDir, 0700, true) && !is_dir($logDir)) {
                Log::add('Clubleaddir audit log: cannot create log dir: ' . $logDir, Log::WARNING, 'com_clubleaddir');
                return;
            }
            $date = Factory::getDate()->toSql();
            $entry = sprintf(
                "[%s] user=%d action=%s id=%d data=%s\n",
                $date,
                (int) $user->id,
                $action,
                (int) $id,
                json_encode($data, JSON_UNESCAPED_SLASHES)
            );
            $file = $logDir . '/audit.log';
            // Serialise rotate+append so concurrent saves cannot interleave
            // against rotation (losing a generation, or appending mid-rename
            // and landing in a rotated file). Same scheme as leaderships.php.
            $lock = @fopen($logDir . '/.lock', 'c');
            if ($lock) {
                flock($lock, LOCK_EX);
            }
            if (is_file($file) && filesize($file) > 10485760) {
                for ($i = 5; $i >= 1; $i--) {
                    $src = $file . ($i === 1 ? '' : '.' . ($i - 1));
                    $dst = $file . '.' . $i;
                    if (is_file($src)) {
                        rename($src, $dst);
                    }
                }
            }
            if (file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) === false) {
                Log::add('Clubleaddir audit log: write failed to: ' . $file, Log::WARNING, 'com_clubleaddir');
            }
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } catch (\Throwable $e) {
            Log::add('Clubleaddir audit log exception: ' . $e->getMessage(), Log::WARNING, 'com_clubleaddir');
        }
    }
    public function setError($msg){ Factory::getApplication()->enqueueMessage($msg,'error'); }
    private function memoryLimitBytes()
    {
        $ini = trim((string) ini_get('memory_limit'));
        if ($ini === '-1') {
            return -1;
        }
        $unit = strtolower(substr($ini, -1));
        $val = (int) $ini;
        switch ($unit) {
            case 'g': return $val * 1024 * 1024 * 1024;
            case 'm': return $val * 1024 * 1024;
            case 'k': return $val * 1024;
            default:  return (int) $ini;
        }
    }
}
