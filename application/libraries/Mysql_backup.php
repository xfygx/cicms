<?php
class CI_Mysql_backup{
	var $fileList=array();
	var $currentPart=1;
	var $result=false;
	var $filename='';
	var $config;
	var $content;
	var $dbName=array();
	var $partSize=2000000;//default is 2MB
	function __construct($config){
		$this->config=$config;
		header("Content-type: text/html;charset=utf-8");
		$this->connect();
	}
	function connect(){
		if(mysql_connect($this->config['host'].':'.$this->config['port'],$this->config['userName'],$this->config['userPassword'])){
			mysql_query("SET NAMES '{$this->config['charset']}'");
			mysql_query("set interactive_timeout=24*3600");
		}else{
			$this->throwException('无法连接到数据库!');
		}
	}
	function setDBName($dbName='*'){
		if($dbName == '*'){
			$rs=mysql_list_dbs();
			$rows=mysql_num_rows($rs);
			if($rows){
				for($i=0; $i < $rows; $i ++){
					$dbName=mysql_tablename($rs,$i);
					$block=array('information_schema','mysql');
					if(! in_array($dbName,$block)){
						$this->dbName[]=$dbName;
					}
				}
			}else{
				$this->throwException('没有任何数据库!');
			}
		}else{
			$this->dbName=func_get_args();
		}
	}
	function getFile($fileName){
		$this->content='';
		$fileName=$this->trimPath($this->config['path'].'/'.$fileName);
		if(is_file($fileName)){
			$ext=strrchr($fileName,'.');
			if($ext == '.sql'){
				$this->content=file_get_contents($fileName);
			}elseif($ext == '.gz'){
				$this->content=implode('',gzfile($fileName));
			}else{
				$this->throwException('无法识别的文件格式!');
			}
		}else{
			$this->throwException('文件不存在!');
		}
	}
	function setFile(){
		$recognize='';
		$recognize=implode('_',$this->dbName);
		if($this->config['isPart']){
			if(empty($this->filename)){
				$this->filename=$this->config['path'].'/'.$recognize.'_'.date('YmdHi'). '_'.mt_rand(100000,999999);
			}
			if($this->config['isCompress']){
				$fileName=$this->filename.'.sql'.'.part'.$this->currentPart;
			}else{
				$fileName=$this->filename.'.part'.$this->currentPart.'.sql';
			}
			$fileName=$this->trimPath($fileName);
			$this->currentPart++;
		}else{
			$fileName=$this->trimPath($this->config['path'].'/'.$recognize.'_'.date('YmdHi'). '_'.mt_rand(100000,999999). '.sql');
		}
		if(!$this->setPath($fileName)){
			$this->throwException("无法创建备份目录目录 '$path'");
		}
		if($this->config['isCompress'] == 0){
			if(! file_put_contents($fileName,$this->content,LOCK_EX)){
				$this->throwException('写入文件失败,请检查磁盘空间或者权限!');
			}
		}else{
			if(function_exists('gzwrite')){
				$fileName .= '.gz';
				if($gz=gzopen($fileName,'wb')){
					gzwrite($gz,$this->content);
					gzclose($gz);
				}else{
					$this->throwException('写入文件失败,请检查磁盘空间或者权限!');
				}
			}else{
				$this->throwException('没有开启gzip扩展!');
			}
		}
		if($this->config['isDownload']){
			if($this->config['isPart']){
				$this->fileList[]=$fileName;
			}else{
				$this->downloadFile($fileName);
			}
		}
		if($this->config['isPart'])$this->content='';
	}
	function trimPath($path){
		return str_replace(array('/','\\','//','\\\\'),'/',$path);
	}
	function setPath($dir,$perm=0777){
		if(file_exists($dir))return true;
		$t=strtr($dir,array('\\'=>'/','//'=>'/'));
		if(strrpos($t,'.')>strrpos($t,'/'))$t=dirname($t);
		$t=rtrim($t,'/');
		$a=array();
		while(($n=strrpos($t,'/'))&&!is_dir($t)){
			$a[]=substr($t,$n+1);
			$t=substr($t,0,$n);
		}
		while($d=array_pop($a)){
			$t.='/'.$d;
			if(!@mkdir($t,$perm))return false;
		}
		return true;
	}
	
	
	
	function downloadFile($fileName){
		$urlFile=basename($fileName);
		print <<<EOT
<script>location.href="index.php?backup/download/$urlFile";</script><noscript><a href="index.php?backup/download/$urlFile">Redirecting...or click here</a></noscript>
EOT;
		/*
		exit;
		
		@ob_end_clean();
		header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Length: '.filesize($fileName));
		header('Content-Disposition: attachment; filename='.basename($fileName));
		readfile($fileName);
		*/
	}
	function backquote($str){
		return "`{$str}`";
	}
	function getTables($dbName){
		@$rs=mysql_list_tables($dbName);
		$rows=mysql_num_rows($rs);
		$dbprefix=$this->config['dbprefix'];
		for($i=0; $i < $rows; $i ++){
			$tbName=mysql_tablename($rs,$i);
			if(substr($tbName,0,strlen($dbprefix)) == $dbprefix){
				$tables[]=$tbName;
			}
		}
		return $tables;
	}
	function chunkArrayByByte($array,$byte=5120){
		$i=0;
		$sum=0;
		$return=array();
		foreach($array as $v){
			$sum += strlen($v);
			if($sum < $byte){
				$return[$i][]=$v;
			}elseif($sum == $byte){
				$return[++ $i][]=$v;
				$sum=0;
			}else{
				$return[++ $i][]=$v;
				$i ++;
				$sum=0;
			}
		}
		return $return;
	}
	
	function backup(){
		$this->content='/* This file is created by MySQLReback '.date('Y-m-d H:i:s'). ' */';
		foreach($this->dbName as $dbName){
			$qDbName=$this->backquote($dbName);
			$rs=mysql_query("SHOW CREATE DATABASE{$qDbName}");
			if($row=mysql_fetch_row($rs)){
				mysql_select_db($dbName);
				$tables=$this->getTables($dbName);
				foreach($tables as $table){
					$table=$this->backquote($table);
					$tableRs=mysql_query("SHOW CREATE TABLE{$table}");
					if($tableRow=mysql_fetch_row($tableRs)){
						$this->content .= "\r\n /* 创建表结构{$table}*/";
						$this->content .= "\r\n DROP TABLE IF EXISTS{$table};/* MySQLReback Separation */{$tableRow[1]};/* MySQLReback Separation */";
						$tableDateRs=mysql_query("SELECT * FROM{$table}");
						$valuesArr=array();
						$values='';
						while($tableDateRow=mysql_fetch_row($tableDateRs)){
							foreach($tableDateRow as &$v){
								$v="'".addslashes($v). "'";
							}
							$valuesArr[]='('.implode(',',$tableDateRow). ')';
						}
						$temp=$this->chunkArrayByByte($valuesArr);
						if(is_array($temp)){
							foreach($temp as $v){
								$values=implode(',',$v). ';/* MySQLReback Separation */';
								if($values != ';/* MySQLReback Separation */'){
									$this->content .= "\r\n /* 插入数据{$table}*/";
									$this->content .= "\r\n INSERT INTO{$table}VALUES{$values}";
								}
							}
						}
						//part backup
						if($this->config['isPart']&&strlen($this->content)>$this->partSize){
							$this->setFile();
						}
					}
				}
			}else{
				$this->throwException('未能找到数据库!');
				return false;
			}
		}
		if(!empty($this->content)){
			$this->setFile();
		}
		$this->result=true;
		return true;
	}
	
	function recover($fileName){
		$this->getFile($fileName);
		if(! empty($this->content)){
			$content=explode(';/* MySQLReback Separation */',$this->content);
			foreach($content as $i => $sql){
				$sql=trim($sql);
				if(! empty($sql)){
					$dbName=$this->dbName[0];
					if(! mysql_select_db($dbName)){
						$this->throwException('不存在的数据库!'.mysql_error());
						return false;
					}
					$rs=mysql_query($sql);
					if($rs){
						if(strstr($sql,'CREATE DATABASE')){
							$dbNameArr=sscanf($sql,'CREATE DATABASE %s');
							$dbName=trim($dbNameArr[0],'`');
							mysql_select_db($dbName);
						}
					}else{
						$this->throwException('备份文件被损坏!'.mysql_error());
						return false;
					}
				}
			}
		}else{
			$this->throwException('无法读取备份文件!');
			return false;
		}
		$this->result=true;
		return true;
	}
	function throwException($error){
		$this->result=false;
		//throw new Exception($error);
		$CI=&get_instance();
		$CI->backview->is_iframe_post(0);
		$CI->backview->failure($error);
		exit;
	}
}