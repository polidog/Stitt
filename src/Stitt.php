<?php
if (defined('DS') === false) {
	define('DS',DIRECTORY_SEPARATOR);
}

/**
 * FLashLiteなSWFのImageIDを変更するためのツール
 * @author polidog
 * @version 0.01
 */
class Stitt {

	/**
	 * 解析するSWFファイル名
	 * @var string
	 */
	protected $swfFilename = null;
	
	/**
	 * 解析するSWFファイルのパス
	 * @var string
	 */
	protected $swfFilePath = './';
	
	
	/**
	 * テンポラリディレクトリを指定
	 * @var string
	 */
	protected $tmpDir = './tmp';


	/**
	 * 拡張し情報
	 * @var array 
	 */
	protected $exts = array(
		'6'		=> 'jpg',
		'21'	=> 'jpg',
		'35'	=> 'jpg',
		'20'	=> 'png',
		'36'	=> 'png',
	);
	
	
	/**
	 * コンストラクタ 
	 */
	public function __construct() {
		
		if ( !file_exists($this->tmpDir) ) {
			// temp dir check
			$this->error('not found temp dir');
		}
		
		// SWFEditor class check
		if ( !class_exists('SWFEditor') ) {
			$this->error('class not found SWFEditor');
		}
		
		// swfmill install check
		$result = exec('type swfmill');
		if ( empty($result) ) {
			$this->error('swfmill not install');
		}
		
	}
	
	/**
	 * ファイル名と名前をセットする
	 * @param type $path
	 * @param type $name 
	 */
	public function setSwfPath($path,$name) {
		$this->swfFilePath = $path;
		$this->swfFilename = $name;
		return $this;
	}
	
	
	/**
	 * イメージIDをリプレイスする 
	 */
	public function replaceImageId() {
		if( empty($this->swfFilename) ) {
			// ファイル名が設定されてない
			$this->output('file name not defined');
		}
		
		// tempファイルを作成
		$this->swf2xml();
		
		// イメージIDを取得する
		$imageInfo = $this->perseSWFImageInfo();
		
		$imageList = array();
		foreach($imageInfo as $key => $value) {
			$imageList[$value['image_id']] = "image_id: {$value['image_id']}";
		}
		
		$oldImageId = $this->input('変更するimageIDを選択してください',$imageList);
		$newImageId = $this->input('変更後のimageIDを選択してください',$imageList);
		$result = $this->input("変更前:{$oldImageId} => 変更後:{$newImageId}",array(
			1 => 'yes', 2 => 'no'
		));
		
		if ( $result == 1 ) {
			// 実行
			$this->replaceObjectId($oldImageId,'__old');
			$this->replaceObjectId($newImageId,'__new');
			$this->replaceObjectId('__old',$newImageId);
			$this->replaceObjectId('__new',$oldImageId);
			$this->xml2swf();
		}
		else {
			$this->output("bye...","end");
		}
		
		$this->output("complite","end");
	}
	
	
	/**
	 * SWFファイルから画像系の解析を行う
	 */
	public function perseSWFImageInfo() {
		
		$filename = $this->getFilepath();
		if ( !file_exists($filename) ) {
			// ファイルが見つからない
			$this->error('file not found. filename='.$filename);
		}
		
		// タグの解析を行う
		$file = file_get_contents($filename);
		$SWFEditor = new SWFEditor();
		$SWFEditor->input($file);
		$tagList = $SWFEditor->getTagList();
		
		$perseData = array();
		foreach ( $tagList as $tagSeqno => $tagBlock ) {
			if ( ! ( $data = $SWFEditor->getTagDetail($tagSeqno) ) ) {
				// データが存在しない場合
				continue;
			}
			
			if ( !isset($data['image_id']) ) {
				// イメージIDがない場合
				continue;
			}
			
			$tag	 = $tagBlock['tag'];
			$tagName = $tagBlock['tagName'];
			$ext	 = null;
			
			// 拡張糸チェック
			if ( isset($this->exts[$tag]) ) {
				$ext = $this->exts[$tag];
			}
			
			$perseData[] = array(
				'image_id'	=> $data['image_id'],
				'ext'		=> $ext,
				'tag_name'  => $tagName,
			);
			
		}
		
		return $perseData;
	}
	
	
	/**
	 * SWFファイルをXMLに変換する 
	 */
	protected function swf2xml() {
		if( empty($this->swfFilename) ) {
			// ファイル名が設定されてない
			$this->output('file name not defined');
		}
		
		$filename =  $this->getFilepath();
		if ( !file_exists($filename) ) {
			$this->error('file not found');
		}
		
		$tempFile = $this->tmpDir.DS.'temp.xml';
		$cmd = "swfmill swf2xml $filename > $tempFile";
		exec($cmd);
	}
	
	
	protected function  xml2swf() {
		$tempFile = $this->tmpDir.DS.'temp.xml';
		if ( !file_exists($tempFile) ) {
			$this->error('ファイルが見つかりません');
		}
		
		$filename =  $this->getFilepath();
		$cmd = "swfmill xml2swf $tempFile > $filename";
		exec($cmd);
	}


	
	/**
	 * XMLで定義されているObjectIdのすべてを取得する 
	 * @return boolean|array 
	 */
	protected function getObjectIds() {
		$tempFile = $this->tmpDir.DS.'temp.xml';
		if ( !file_exists($tempFile) ) {
			$this->error('ファイルが見つかりません');
		}
		$xml = file_get_contents($tempFile);
		$matches = null;
		if ( preg_match_all('/objectID="*(.*?)"/', $xml, $matches) ) {
			if ( isset($matches[1]) && is_array($matches[1])) {
				$ret = array();
				foreach( $matches[1] as $value ) {
					$value = (int)$value;
					$ret[$value] = $value;
				}
				$a = array_shift($ret);
				array_unshift($ret, $a);
				return $ret;
			}
		}
		return false;
	}
	
	
	/**
	 * objectIDを書き換える
	 * @param string $oldId
	 * @param string $newId
	 * @return boolean 
	 */
	protected function replaceObjectId($oldId, $newId) {
		$tempFile = $this->tmpDir.DS.'temp.xml';
		if ( !file_exists($tempFile) ) {
			$this->error('ファイルが見つかりません');
		}
		$xml = file_get_contents($tempFile);
		unlink($tempFile);
		
		
		$xml = str_replace("objectID=\"{$oldId}\"", "objectID=\"{$newId}\"", $xml,$count);
		if ( $count < 1 ) {
			return false;
		}
		
		if ( file_put_contents($tempFile, $xml,FILE_APPEND) === false ) {
			return false;
		}
		return true;
	}
	
	
	
	protected function getFilepath() {
		return $this->swfFilePath.DS.$this->swfFilename;
	}
	
	/**
	 * エラー処理
	 * @param string $message 　
	 */
	protected function error($message) {
		$this->output($message,'error');
		exit;
	}
	
	
	/**
	 * テキストを取得する
	 * @param string $message メッセージ
	 * @param string $prefix 　接頭辞
	 */
	public function output($message,$prefix=null) {
		$output = "";
		
		if ( !is_null($prefix) ) {
			$output .= "[$prefix]";
		}
		$output .= $message;
		echo $output."\n";
	}
	
	
	/**
	 * 入力処理
	 * @param string $message メッセージ
	 * @param array $list 選択肢
	 */
	protected function input($message,$list = array()) {
		$stdin = fopen("php://stdin", "r");
		if ( !$stdin ) {
			$this->error('not stdin');
		}
		
		if ( empty($list) || !is_array($list) ) {
			$this->error('not list type');
		}
		
		
		$self = $this;
		$comandListFn = function() use ($list, $self) {
			$self->output('command list','command');
			foreach ( $list as $key => $value ) {
				$self->output("\t".$value,$key);
			}
		};
		
		$this->output($message,'input');
		$comandListFn();
		
		$input = false;
		while(true) {
			$input = trim(fgets($stdin, 64));
			if ( !isset($list[$input]) ) {
				
				if ( $list['default'] ) {
					$input = "default";
				}
				else {
					$this->output('正しいコマンドを入力してださい');
					$comandListFn();
					continue;
				}
			}
			
			if ( !empty($input) ) {
				break;
			}
		}
		return $input;
	}
}