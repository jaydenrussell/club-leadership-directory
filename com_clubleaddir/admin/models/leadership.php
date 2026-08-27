<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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
        $record=['name'=>$data['name'],'type'=>$data['type'],'role'=>$data['role']??'','league_name'=>$data['league_name']??'','term'=>$data['term']??'','start_year'=>(int)($data['start_year']??0),'end_year'=>(int)($data['end_year']??0),'bio'=>$data['bio']??'','email'=>$data['email']??'','phone'=>$data['phone']??'','contact_id'=>(int)($data['contact_id']??0),'vacant'=>!empty($data['vacant'])?1:0,'ordering'=>(int)($data['ordering']??0),'published'=>isset($data['published'])?(int)$data['published']:1,'status'=>$data['status']??'active'];
        $app=Factory::getApplication(); $files=$app->input->files->get('jform',[],'array');
        if(!empty($files['photo']['name'])){
            if(($files['photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
            if(!is_uploaded_file($files['photo']['tmp_name'])){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
            $photoPaths=$this->handlePhotoUpload($files['photo']); if($photoPaths===false) return false;
            $record['photo_full']=$photoPaths[0]; $record['photo']=$photoPaths[1];
        }elseif(!empty($data['id'])){
            $existing=$this->store->getById((int)$data['id']); if($existing){ $record['photo']=$existing->photo; $record['photo_full']=$existing->photo_full; }
        }
        $record['modified']=$date; $record['modified_by']=$userId;
        if(!empty($data['id'])){
            $existing=$this->store->getById((int)$data['id']); if($existing){ $record['created']=$existing->created; $record['created_by']=$existing->created_by; }
            $result=(bool)$this->store->update((int)$data['id'],$record);
        }else{ $record['created']=$date; $record['created_by']=$userId; $result=(bool)$this->store->insert($record); }
        if($result && $this->store!==null) $this->store->reorderAll($record['type']??null);
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
        // Dimension check BEFORE loading into RAM — cheap-host OOM guard
        $dims=@getimagesize($fileInfo['tmp_name']); if($dims && ($dims[0]>4000 || $dims[1]>4000)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_DIMENSIONS')); return false; }
        if($dims && ($dims[0]*$dims[1] > 16000000)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_DIMENSIONS')); return false; } // 16 MP cap
        $mime=null; if(class_exists('finfo')){ try{$f=new \finfo(FILEINFO_MIME_TYPE); $mime=$f->file($fileInfo['tmp_name']);}catch(\Throwable $e){} }
        if(!$mime) $mime=mime_content_type($fileInfo['tmp_name']);
        if(!in_array($mime,$allowedMimes,true)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_INVALID_TYPE')); return false; }
        $ext='jpg'; switch($mime){ case 'image/png': $ext='png'; break; case 'image/gif': $ext='gif'; break; case 'image/webp': $ext='webp'; break; }
        $destDir=JPATH_ROOT.'/images/clubleaddir/photos'; if(!is_dir($destDir) && !@mkdir($destDir,0700,true) && !is_dir($destDir)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
        try{ $base='photo_'.time().'_'.bin2hex(random_bytes(4)); }catch(\Throwable $e){ $base='photo_'.time().'_'.bin2hex(openssl_random_pseudo_bytes(4)); }
        $orig=$base.'.'.$ext; $square=$base.'_sq.'.$ext; $origPath=$destDir.'/'.$orig; $squarePath=$destDir.'/'.$square;
        if(!@move_uploaded_file($fileInfo['tmp_name'],$origPath)){ $this->setError(Text::_('COM_CLUBLEADDIR_ERROR_PHOTO_UPLOAD_FAILED')); return false; }
        @chmod($origPath,0600); $this->makeSquareCrop($origPath,$squarePath,400); if(is_file($squarePath)) @chmod($squarePath,0600);
        return ['/images/clubleaddir/photos/'.$orig,'/images/clubleaddir/photos/'.$square];
    }
    protected function makeSquareCrop($src,$dest,$size=400){
        if(!function_exists('imagecreatefromstring')) return false;
        $img=@imagecreatefromstring(@file_get_contents($src)); if($img===false) return false;
        $sw=imagesx($img); $sh=imagesy($img); if(!$sw||!$sh){ imagedestroy($img); return false; }
        $side=min($sw,$sh); $srcX=(int)(($sw-$side)/2); $srcY=(int)(($sh-$side)*0.38); if($srcY<0) $srcY=0;
        $out=imagecreatetruecolor($size,$size); if(!$out){ imagedestroy($img); return false; }
        imagefill($out,0,0,imagecolorallocate($out,255,255,255)); imagesavealpha($out,true); imagealphablending($out,false);
        imagecopyresampled($out,$img,0,0,$srcX,$srcY,$size,$size,$side,$side);
        $ok=false; $low=strtolower($dest);
        if(substr($low,-4)==='.png') $ok=imagepng($out,$dest,8);
        elseif(substr($low,-4)==='.gif') $ok=imagegif($out,$dest);
        elseif(substr($low,-5)==='.webp' && function_exists('imagewebp')) $ok=imagewebp($out,$dest,90);
        else $ok=imagejpeg($out,$dest,90);
        imagedestroy($img); imagedestroy($out); return (bool)$ok;
    }
    public function delete(array $pks){ $ok=true; foreach($pks as $pk) if(!$this->store->delete((int)$pk)) $ok=false; return $ok; }
    public function publish(array $pks,$state=1){ $ok=true; foreach($pks as $pk) if(!$this->store->setPublished((int)$pk,(int)$state)) $ok=false; return $ok; }
    public function trash(array $pks){ return $this->publish($pks,-2); }
    public function reorderSingle($id,$dir){ return $this->store->reorderSingle((int)$id,(int)$dir); }
    public function saveOrder(array $pks,array $order){
        if($this->store===null) return false;
        // Clamp ordering to prevent N=999999 UPDATE storm from tampered POST
        foreach($pks as $i=>$pk){ $ord=isset($order[$i])?(int)$order[$i]:0; $ord=max(0,min(9999,$ord)); $this->store->setOrdering((int)$pk,$ord); }
        return true;
    }
    public function setError($msg){ Factory::getApplication()->enqueueMessage($msg,'error'); }
}
