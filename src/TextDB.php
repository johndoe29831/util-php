<?php

namespace Jd29;

class TextDB {

    /**
     * options
     *
     * @var array
     */
    protected $_OPTIONS = array();

 	/**
	 * root data dir
	 * 
	 * @var string
	 */
	protected $_ROOT_DIR = null;

	/**
	 * error status
	 * 
	 * @var bool
	 */
	protected $_ERROR_STATUS =  false;
	
	/**
	 * error message
	 * 
	 * @var array
	 */
	protected $_ERROR_MESSAGE = array();

	/**
	 * transaction status
	 * 
	 * @var bool
	 */
	protected $_TRANSACTION_STATUS =  false;


	/**
	 * table data
	 * 
	 * @var array
	 */
	protected $_TABLE_DATA = array();
	
	/**
	 * update table
	 * 
	 * @var array
	 */
	protected $_TABLE_UPDATE = array();
	
	/**
	 * delete table
	 * 
	 * @var array
	 */
	protected $_TABLE_DROP = array();

	/**
	 * trigger default order
	 * 
	 * @var array
	 */
	protected $_TRIGGER_DEFAULT_ACTION = array('delete', 'delete_deep', 'rename', 'callback');
	
	/**
	 * commit trigger order
	 * 
	 * @var array
	 */
	protected $_COMMIT_TRIGGER_ORDER = array();
	
	/**
	 * commit trigger action 
	 * 
	 * @var array
	 */
	protected $_COMMIT_TRIGGER_ACTION = array();
	
	/**
	 * rollback trigger order
	 * 
	 * @var array
	 */
	protected $_ROLLBACK_TRIGGER_ORDER = array();
	
	/**
	 * rollback trigger action
	 * 
	 * @var array
	 */
	protected $_ROLLBACK_TRIGGER_ACTION = array();
	
	
	/**
	 * flag action
	 * @var array
	 */
	protected $_FLAG_ACTION = array();


    /**
     * construct function
     *
     * @param string $root_dir
     * @param array $options
     */
    public function __construct ($root_dir, $options = array()) {
        // オプションの初期設定
        $this->_options_defaults($options);

		// トリガーの初期設定
		$this->_COMMIT_TRIGGER_ORDER = $this->_ROLLBACK_TRIGGER_ORDER = $this->_TRIGGER_DEFAULT_ACTION;
		foreach ($this->_TRIGGER_DEFAULT_ACTION as $act) {
			$this->_COMMIT_TRIGGER_ACTION[$act] = array();
			$this->_ROLLBACK_TRIGGER_ACTION[$act] = array();
		}
		
		// ルートディレクトリの設定
		if (!is_string($root_dir) or !is_dir($root_dir)) {
			return $this->_add_error(sprintf('%s: 指定のディレクトリは存在しません [%s]', 'root_dir', $root_dir));
		}
		$this->_ROOT_DIR = $this->_get_real_path($root_dir);
        
    }

    /**
     * オプションの初期設定
     *
     * @param array $options
     * @return void
     */
    private function _options_defaults ($options) {
        $defaults = [
            'table_extension' => '.php',
            'table_name_pattan' => '#^[A-Za-z0-9][A-Za-z0-9_\-\.]+$#',
			'field_name_pattan' => '#^[A-Za-z0-9][A-Za-z0-9_\-\.]+$#',
            'file_permisson'  => 0666,
            'dir_permission'  => 0777,
            'dir_tree_max' => 20,
            'lock_name' => '.textdb_lock',
            'lock_life_time' => '30',
            'lock_retry_max' => 5,
			'write_retry_max' => 5,
			'write_retry_usleep' => 200000,
            'flag_extension' => '.flag',
            'flag_name_pattan' => '#^[A-Za-z0-9][A-Za-z0-9_\-\.]+$#',
        ];

        $this->_OPTIONS = $options + $defaults;
    }

    /**
     * オプションの取得
     *
     * @param string $key
     * @return mixed
     */
    public function get_option ($key) {
        return isset($this->_OPTIONS[$key]) ? $this->_OPTIONS[$key] : null;
    }

    /**
     * オプションの設定
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set_option ($key, $value) {
        $this->_OPTIONS[$key] = $value;
    }

	/**
	 * エラーの追加
	 * 
	 * @param string $message
	 * @return bool
	 */
	protected function _add_error ($message='') {
		$this->_ERROR_STATUS = true;
		if (is_string($message) and strlen($message)) {
			$this->_ERROR_MESSAGE[] = $message;
		} else if (is_array($message)) {
			foreach ($message as $s) {
				$this->_add_error($s);
			}
		}
		return false;
	}
	
	/**
	 * エラーのクリアー
	 * 
	 * @return void
	 */
	protected function _clear_error () {
		$this->_ERROR_STATUS = false;
		$this->_ERROR_MESSAGE = array();
		return false;
	}
	
	/**
	 * エラーの確認
	 * 
	 * @return bool
	 */
	public function is_error () {
		return $this->_ERROR_STATUS;
	}
	
	/**
	 * エラーの取得
	 * 
	 * @return array
	 */
	public function get_error () {
		return $this->_ERROR_MESSAGE;
	}

	/**
	 * リアルパスの取得
	 * 
	 * @param string $path
	 * @return mixed
	 */
	protected function _get_real_path ($path) {
		$path = realpath($path);
		if (is_dir($path) and !$this->_str_endswith($path, DIRECTORY_SEPARATOR)) {
			$path .= DIRECTORY_SEPARATOR; 
		}
		return $path;
	}

	/**
	 * 仮想ファイルパスの取得
	 * 
	 * @param string $path
	 * @return mixed
	 */
	protected function _get_virtual_dir_path ($path) {
        $dir = $this->resolvePath($path);
		if (!$this->_str_endswith($dir, DIRECTORY_SEPARATOR)) {
			$dir .= DIRECTORY_SEPARATOR; 
		}
		return $dir;
	}

    /**
     * resolvePath
     * 
     * @see https://www.php.net/manual/ja/function.realpath.php
     * @param string $path
     * @return void
     */
    public function resolvePath($path) {
        if(DIRECTORY_SEPARATOR !== '/') {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        }
        $search = explode('/', $path);
        $search = array_filter($search, function($part) {
            return $part !== '.';
        });
        $append = array();
        $match = false;
        while(count($search) > 0) {
            $match = realpath(implode('/', $search));
            if($match !== false) {
                break;
            }
            array_unshift($append, array_pop($search));
        };
        if($match === false) {
            $match = getcwd();
        }
        if(count($append) > 0) {
            $match .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $append);
        }
        return $match;
    }
	
	/**
	 * 文字列の接尾辞の確認
	 * 
	 * @param string $str
	 * @param mixed $sub
	 * @return bool
	 */
	protected function _str_endswith($str, $sub) {
		if (is_array($sub)) {
			foreach ($sub as $n) {
				if (substr($str, strlen($str) - strlen($n)) === $n) {
					return true;
				}
			}
			return false;
		} else {
			return (substr($str, strlen($str) - strlen($sub)) === $sub);
		}
	}

	/**
	 * 文字列の等しさ
	 * 
	 * @param string $str1
	 * @param string $str2
	 * @return bool
	 */
	protected function _str_equal ($str1, $str2) {
		return (strcmp($str1, $str2) === 0);
	}
	
	/**
	 * 正の整数の確認
	 * 
	 * @param float $val
	 * @return bool
	 */
	protected function _is_primary_no ($val) {
		if (!is_numeric($val)) return false;
		$no = abs(floatval($val));
		return (!$no or !$this->_str_equal($val, $no)) ? 0 : $no;
	}
	
	/**
	 * 正の整数の確認
	 * 
	 * @param float $val
	 * @return bool
	 */
	public function check_primary_no ($val) {
        return $this->_is_primary_no($val);
	}

	/**
	 * ルートディレクトリの取得
	 * 
	 * @return string
	 */
	public function get_root_dir () {
		return $this->_ROOT_DIR;
	}
	
	/**
	 * ロックディレクトリの取得
	 * 
	 * @return string
	 */
	public function get_lock_dir () {
		return $this->_ROOT_DIR . $this->get_option('lock_name');
	}
	
	/**
	 * 削除
	 * 
     * @param string $dir
	 * @return bool
	 */
	protected function _rmdir_deep ($dir) {
		if (!file_exists($dir)) return true;
		if (!is_dir($dir)) return unlink($dir);
		
		if ($fh = @opendir($dir)) {
			while (($file = readdir($fh)) !== false) {
				if (in_array($file, array('.', '..'))) continue;
				if (!$this->_rmdir_deep($dir . DIRECTORY_SEPARATOR . $file)) return false;
			}
			
			closedir($fh);
		}
		
		return @rmdir($dir);
	}
	
	/**
	 * トランザクションの状態
	 * 
	 * @return bool
	 */
	public function is_transaction () {
		return $this->_TRANSACTION_STATUS;
	}
	
	/**
	 * トランザクションの確認
	 * 
	 * @return bool
	 */
	public function check_transaction () {
		if ($this->is_transaction()) return true;
		$lock_dir = $this->get_lock_dir();
		if (is_dir($lock_dir)) {
			if ((time() - filemtime($lock_dir)) > $this->get_option('lock_life_time')) {
				return false;
			} else {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * 配列の確認
	 * 
	 * @param mixed $var
	 * @return bool
	 */
	public function is_array($var) {
		if (!is_array($var) or !count($var)) return false;
		if (array_keys($var) !== range(0, count($var) - 1)) return false;
		
		return true;
	}

	/**
	 * トランザクション 開始
	 * 
	 * @return bool
	 */
	public function BEGIN () {
		// トランザクションの確認
		if ($this->_TRANSACTION_STATUS) {
			return $this->_add_error('トランザクションは開始されています');
		}
		
		// ロックパスの取得
		$lock_dir = $this->get_lock_dir();
		if (!$lock_dir) {
			return $this->_add_error('ロックディレクトリを取得できませんでした');
		}
		
		// 書き込みの確認
		$lock_base_dir = dirname($lock_dir);
		if (!is_writable($lock_base_dir)) {
			return $this->_add_error(sprintf('ロックディレクトリを書き込みできません [%s]', $lock_base_dir));
		}
		
		
		// ロックの現状確認
		if (is_dir($lock_dir)) {
			if ((time() - filemtime($lock_dir)) > $this->get_option('lock_life_time')) {
				if (!@rmdir($lock_dir)) {
					return $this->_add_error(sprintf('ロックディレクトリの削除に失敗しました。[%s]', $lock_dir));
				}
			}
		}
		
		// ロックの開始
		$locked = @mkdir($lock_dir);
		if (!$locked) {
			$start_time = time();
			do {
				if (time() - $start_time > $this->get_option('lock_retry_max')) break;
				$locked = @mkdir($lock_dir);
				if ($locked) break;
				sleep(1);
			} while (!$locked);
			
		}
		
		if ($locked) {
			@chmod($lock_dir, $this->get_option('dir_permission'));
			$this->_TRANSACTION_STATUS = true;
			
			register_shutdown_function(array($this, 'ROLLBACK'));
			return true;
		} else {
			return $this->_add_error('ロックに失敗しました。');
		}
		
	}
	
	/**
	 * トランザクション ロールバック
	 *  
	 * @return bool
	 */
	public function ROLLBACK () {
		// トランザクションの確認
		if (!$this->_TRANSACTION_STATUS) {
			return $this->_add_error('トランザクションは開始されていません');
		}
		
		// 変数の初期化
		$this->_TABLE_DATA = array();
		$this->_TABLE_UPDATE = array();
		$this->_TABLE_DROP = array();
		
		// トリガーの実行
		foreach ($this->_ROLLBACK_TRIGGER_ORDER as $act) {
			if (in_array($act, $this->_TRIGGER_DEFAULT_ACTION)) {
				call_user_func(array($this, '_trigger_' . $act), $this->_ROLLBACK_TRIGGER_ACTION[$act]);
			}
		}
		
		// トリガーの初期化
		$this->commit_clear_trigger();
		$this->rollback_clear_trigger();
		
		
		// フラグの初期化
		$this->_FLAG_ACTION = array();
		
		
		// ロックパスの取得
		$lock_dir = $this->get_lock_dir();
		if (is_dir($lock_dir)) {
			// ロックの解除
			@rmdir($lock_dir);
		}
		
		$this->_TRANSACTION_STATUS = false;

		return true;
	}
	
	/**
	 * トリガー ファイル・ディレクトリの削除
	 * 
	 * @param array $files
	 * @return void
	 */
	protected function _trigger_delete ($files) {
		
		sort($files, SORT_STRING);
		$files = array_reverse($files);
		
		foreach ($files as $file) {
			if (is_file($file)) {
				@unlink($file);
				
			} else if (is_dir($file)) {
				if ($dh = @opendir($file)) {
					$f_flag = false;
					while (($f = readdir($dh)) !== false) {
						if (in_array($f, array('.', '..'))) continue;
						$f_flag = true;
						break;
					}
					closedir($dh);
					
					if (!$f_flag) {
						@rmdir($file);
					}
				}
			}
		}
	}
	
	
	/**
	 * トリガー ファイル・ディレクトリの削除
	 * 
	 * @param array $files
	 * @return void
	 */
	protected function _trigger_delete_deep ($dirs) {
	 	foreach ($dirs as $dir) {
	 		$this->_rmdir_deep($dir);
	 	}
	 }
	 
	/**
	 * トリガー ファイル・ディレクトリの名前変更
	 * 
	 * @param array $files
	 * @return void
	 */
	protected function _trigger_rename ($files) {
		foreach ($files as $f1 => $f2) {
			if (is_file($f1)) {
				if (is_file($f2)) @unlink($f2);
				@rename($f1, $f2);
			}
		}
	}
	
	/**
	 * トリガー コールバックの実行
	 * 
	 * @param array $funcs
	 * @return void
	 */
	protected function _trigger_callback ($funcs) {
		foreach ($funcs as $n) {
			if (is_callable($n)) call_user_func($n);
		}
	}
	
	/**
	 * COMMIT トリガーのクリアー
	 * 
	 * @return void
	 */
	public function commit_clear_trigger () {
		$this->_COMMIT_TRIGGER_ORDER = $this->_TRIGGER_DEFAULT_ACTION;
		foreach ($this->_TRIGGER_DEFAULT_ACTION as $act) {
			$this->_COMMIT_TRIGGER_ACTION[$act] = array();
		}
	}
	
	/**
	 * ROLLBACK トリガーのクリアー
	 * 
	 * @return void
	 */
	public function rollback_clear_trigger () {
		$this->_ROLLBACK_TRIGGER_ORDER = $this->_TRIGGER_DEFAULT_ACTION;
		foreach ($this->_TRIGGER_DEFAULT_ACTION as $act) {
			$this->_ROLLBACK_TRIGGER_ACTION[$act] = array();
		}
	}
	
	/**
	 * COMMIT トリガーのアクション追加
	 * @param string $act
	 * @param mixed $val1 
	 * @param string $val2
	 * @return void
	 */
	public function COMMIT_ADD_TRIGGER ($act, $val1, $val2=null) {
		if (in_array($act, array('delete', 'delete_deep', 'callback'))) {
			$this->_COMMIT_TRIGGER_ACTION[$act][] = $val1;
		}
		if (in_array($act, array('rename'))) {
			$this->_COMMIT_TRIGGER_ACTION[$act][$val1] = $val2;
		}
	}
	
	/**
	 * ROLLBACK トリガーのアクション追加
	 * 
	 * @param string $act
	 * @param mixed $val1 
	 * @param string $val2
	 * @return void
	 */
	public function ROLLBACK_ADD_TRIGGER ($act, $val1, $val2=null) {
		if (in_array($act, array('delete', 'delete_deep',  'callback'))) {
			$this->_ROLLBACK_TRIGGER_ACTION[$act][] = $val1;
		}
		if (in_array($act, array('rename'))) {
			$this->_ROLLBACK_TRIGGER_ACTION[$act][$val1] = $val2;
		}
	}
	
	/**
	 * トランザクションの終了
	 * 
	 * @return bool
	 */
	public function COMMIT () {
		// トランザクションの確認
		if (!$this->_TRANSACTION_STATUS) {
			return $this->_add_error('トランザクションは開始されていません');
		}
		
		// テーブルパスの取得
		$table_paths = array();
		foreach ($this->_TABLE_UPDATE as $table_path => $bool) {
			if (!$bool) continue;
			if (array_key_exists($table_path, $this->_TABLE_DROP)) continue;
			
			$table_paths[] = $table_path;
		}

		// テーブルの書き込み
		if (!$this->_write_table($table_paths)) return false;
		
		// テーブルの削除の登録
		foreach ($this->_TABLE_DROP as $table_path => $bool) {
			if (!$bool) continue;
			
			$this->COMMIT_ADD_TRIGGER('delete', $table_path);
		}
		
		// 変数の初期化
		$this->_TABLE_UPDATE = array();
		$this->_TABLE_DROP = array();
		
		
		// トリガーの実行
		foreach ($this->_COMMIT_TRIGGER_ORDER as $act) {
			if (in_array($act, $this->_TRIGGER_DEFAULT_ACTION)) {
				call_user_func(array($this, '_trigger_'.$act), $this->_COMMIT_TRIGGER_ACTION[$act]);
			}
		}
		
		// フラグの実行
		foreach ($this->_FLAG_ACTION as $action) {
			if ($action['type'] == 'clear') {
				if (!file_exists($action['source'])) {
					continue;
				}
				$this->_flag_clear($action['source'], true);
				
			} else if ($action['type'] == 'add') {
				if (!file_exists($action['source'])) {
					if (!$this->_make_data_dir($action['source'])) continue;
					@touch($action['source']);
					@chmod($action['source'], $this->get_option('file_permission'));
				}
			} else if ($action['type'] == 'delete') {
				if (file_exists($action['source'])) {
					@unlink($action['source']);
				}
			}
		}
		
		// トリガーの初期化
		$this->commit_clear_trigger();
		$this->rollback_clear_trigger();
		
		// フラグの初期化
		$this->_FLAG_ACTION = array();
		
		// ロックパスの取得
		$lock_dir = $this->get_lock_dir();
		if (is_dir($lock_dir)) {
			// ロックの解除
			@rmdir($lock_dir);
		}
		
		$this->_TRANSACTION_STATUS = false;
		
		return true;
	}

	/**
	 * テーブルの作成
	 * 
	 * @param string $dir 
	 * @param string $table
	 * @param array $field
	 * @param string $type
	 * @return bool
	 */
	public function TABLE_CREATE ($dir, $table_name, $field, $type='list') {
		// データディレクトリの確認
		if (!$this->_check_data_dir($dir)) {
			return false;
		}
		
		// テーブル名の確認
		if (!$this->_check_table_name($table_name)) {
			return false;
		}
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) {
			return false;
		}
		
		// テーブルの存在の確認
		if ($this->TABLE_EXISTS($dir, $table_name)) {
			return $this->_add_error(sprintf('指定のテーブルはすでに存在しています [%s]', $table_path));
		}
		
		// 構造の確認
		if (!$this->is_array($field)) {
			return $this->_add_error(sprintf('フィールドを正しく認識できません [%s]', $table_path));
		}
		
		$field_ = array();
		for ($i=0, $i_max=count($field); $i<$i_max; $i++) {
			$f = $field[$i];

			if ($this->_check_field_name($f)) {
				$field_[] = $f;
			}
			
			#if (is_string($f) and strlen($f) and strpos($f, " ") === false and strpos($f, "\n") === false and strpos($f, "\r") === false and strpos($f, "\t") === false) {
			#	$field_[] = $f;
			#}
			
		}
		
		if (count($field) != count($field_)) {
			return $this->_add_error(sprintf('フィールドを設定が正しくありません [%s]', $table_path));
		}
		
		// テーブルの設定
		$table_data = array(
			'primary_no' => floatval(0),
			'field' => $field_,
			'order' => array(),
			'data'  => array(),
			'cache' => array(),
			'trans' => true,
			'type'  => in_array($type, array('list', 'hash')) ? $type : 'list',
			'mtime' => null,
		);
		
		$this->_TABLE_DATA[$table_path] = $table_data;
		$this->_TABLE_UPDATE[$table_path] = true;
		
		return true;
	}


	
	/**
	 * データディレクトリの確認
	 * 
	 * @param string $path
	 * @return bool
	 */
	protected function _check_data_dir (&$dir) {
		// 実在の確認
		if (file_exists($dir)) {
			if (!is_dir($dir))  {
				return $this->_add_error(sprintf('ディレクトリではありません [%s]', $dir));
			}
			
			$path = $this->_get_real_path($dir);
			
		} else {
			$path = $this->_get_virtual_dir_path($dir);
			
		}
		
		// ROOT_DIR以下の確認
		if (strpos($path, $this->_ROOT_DIR) !== 0) {
			return $this->_add_error(sprintf('ルートディレクトリ以下以外のディレクトリです [%s : %s]', __FUNCTION__, $path));
		}

		// 階層数の確認
		$tree_path = strlen($this->_ROOT_DIR) == strlen($path) ? '' : substr($path, strlen($this->_ROOT_DIR));
		$tree_cnt = 0;
		foreach (explode(DIRECTORY_SEPARATOR, $tree_path) as $tree_dir) {
			if (strlen($tree_dir)) $tree_cnt++; 
		}
		if ($tree_cnt > $this->get_option('dir_tree_max')) {
			return $this->_add_error(sprintf('ディレクトリの階層が制限を超えています [%s]', $path));
		}
		
		
		$dir = $path;
		return true;
	}
	
	/**
	 * データディレクトリの作成
	 * 
	 * @param string $table_path
	 * @return bool
	 */
	protected function _make_data_dir ($table_path) {
		$data_dir = dirname($table_path) . DIRECTORY_SEPARATOR;
		
		$tree_path = strlen($this->_ROOT_DIR) == strlen($data_dir) ? '' : substr($data_dir, strlen($this->_ROOT_DIR));
		
		$current_dir = $this->_ROOT_DIR;
		if (!is_writable($current_dir)) {
			return $this->_add_error(sprintf('ディレクトリに書き込みができません [%s]', $current_dir));
		}
		
		foreach (explode(DIRECTORY_SEPARATOR, $tree_path) as $dir) {
			if (!strlen($dir)) continue;
			
			$current_dir .= $dir . DIRECTORY_SEPARATOR;
			
			if (!@is_dir($current_dir)) {
				if (!@mkdir($current_dir)) {
					return $this->_add_error(sprintf('ディレクトリを作成できません [%s]', $current_dir));
				}
				
				@chmod($current_dir, $this->get_option('dir_permission'));
				
				$this->ROLLBACK_ADD_TRIGGER('delete', $current_dir);
			}
			
			if (!is_writable($current_dir)) {
				return $this->_add_error(sprintf('ディレクトリに書き込みができません [%s]', $current_dir));
			}
			
		}
		
		return true;
	}
	
	/**
	 * テーブル名の確認
	 * 
	 * @param string $name
	 * @return bool
	 */
	protected function _check_table_name ($name) {
		$flag = false;
		do {
			if (!is_string($name)) {
				break;
			}
			if (!strlen($name)) {
				break;
			}
			if (!preg_match($this->get_option('table_name_pattan'), $name)) {
				break;
			}
			if ($this->_str_endswith(strtolower($name), strtolower($this->get_option('table_extension')))) {
				break;
			}
			
			$flag = true;
		} while (false);

		if (!$flag) {
			return $this->_add_error(sprintf('テーブル名に利用できない文字を含んでいます [%s]', $name));
		} else {
			return true;
		}
	}

	/**
	 * フィールド名の確認
	 * 
	 * @param string $name
	 * @return bool
	 */
	protected function _check_field_name ($name) {
		$flag = false;
		do {
			if (!is_string($name)) {
				break;
			}
			if (!strlen($name)) {
				break;
			}
			if (!preg_match($this->get_option('field_name_pattan'), $name)) {
				break;
			}
			$flag = true;
		} while (false);

		if (!$flag) {
			return $this->_add_error(sprintf('フィールド名に利用できない文字を含んでいます [%s]', $name));
		} else {
			return true;
		}
	}


	
	/**
	 * テーブルのファイルパスを取得
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param string $table_path
	 * @return bool
	 */
	public function get_table_path ($dir, $table_name, &$table_path) {
		// テーブル名の確認
		if (!strlen($table_name)) return false;
		
		// テーブル名の確認
		if (!$this->_check_table_name($table_name)) return false;
		
		// ルート以下の確認
		$dir = $this->_get_virtual_dir_path($dir);
		
		if (strpos($dir, $this->_ROOT_DIR) !== 0) {
			return $this->_add_error(sprintf('ルートディレクトリ以下以外のディレクトリです [%s : %s]', __FUNCTION__, $dir));
		}
		
		$table_path = $dir . $table_name . $this->get_option('table_extension');
		
		return true;
	}
	
	/**
	 * テーブルの存在の確認
	 * 
	 * @param string $dir 
	 * @param string $table_name
	 * @return bool
	 */
	public function TABLE_EXISTS ($dir, $table_name) {
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		return (is_file($table_path) or array_key_exists($table_path, $this->_TABLE_UPDATE));
	}
	
    /**
     * fputcsv
     *
     * @param array $fields
     * @return string
     */
    public function fputcsv(array $fields) {
        $output = array();
        foreach ($fields as $field => $value) {
            $output[] = $this->fputcsv_convert($value);
        }

        #return implode(',', $output) . "\r\n";
		return implode(',', $output) . "\n";
    }

    /**
     * fputcsv convert
     *
     * @param string $value
     * @param boolean $dc
     * @return string
     */
    public function fputcsv_convert($value, $dc = true) {
        if (is_null($value)) {
            return '';
        }
        if ($dc) {
            $value = str_replace('"', '""', $value);
        }

        $value = trim(trim($value), "\'");
        $value = str_replace("\\'", "'", $value);
        #$value = str_replace('{CM}', ',', $value);
        #$to = ' ';
        #$value = strtr($value, array("\r\n" => $to, "\r" => $to, "\n" => $to));
		#$data[] = str_replace(array('#', "\x0D\x0A", "\x0D", "\x0A", "\x09"), array('##', '#n', '#n', '#n', '#t'), $this->_TABLE_DATA[$table_path]['data'][$data_key][$f_i]);
		#$value = str_replace(array('#', "\x0D\x0A", "\x0D", "\x0A", "\x09"), array('##', '#n', '#n', '#n', '#t'), $value);
		#$value = str_replace(array('#', "\x0D\x0A", "\x0D", "\x0A", "\x09"), array('##', '#n', '#n', '#n', '#t'), $value);
		$value = str_replace(array('\\', "\x0D\x0A", "\x0D", "\x0A", "\x09", "\x07", "\x0C"), array('\\\\', '\\n', '\\n', '\\n', '\\t', '', ''), $value);
        $value = '"' . $value . '"';
        return $value;
    }


	/**
	 * テーブルの書き込み
	 * 
	 * @param mixed $table_path
	 * @return bool
	 */
	protected function _write_table ($table_path) {


		$table_paths = array();
		if (is_array($table_path)) {
			$table_paths = $table_path;
		} else if (is_string($table_path)) {
			$table_paths[] = $table_path;
		}
		
		if (!count($table_paths)) return false;
		
		// データの確認
		for ($i=0, $i_max=count($table_paths); $i<$i_max; $i++) {
			$table_path = $table_paths[$i];
			
			if (!array_key_exists($table_path, $this->_TABLE_DATA)) {
				return $this->_add_error(sprintf('データの取得に失敗しました [%s]', $table_path));
			}
		}
		
		// ディレクトリの確認
		for ($i=0, $i_max=count($table_paths); $i<$i_max; $i++) {
			if (!$this->_make_data_dir($table_paths[$i])) return false;
		}

		// キャッシュをクリア
		clearstatcache();		
		
		$fp = array();
		$flag = false;
		while (true) {
			for ($i=0, $i_max=count($table_paths); $i<$i_max; $i++) {
				$table_path = $table_paths[$i];
				
				// ファイルを開く
				if (is_dir($table_path)) {
					$this->_add_error(sprintf('テーブルファイルの生成に失敗しました [%s]', $table_path));
					break 2;
				}
				
				if (!is_file($table_path)) {
					if (!touch($table_path)) {
						$this->_add_error(sprintf('テーブルファイルの生成に失敗しました [%s]', $table_path));
						break 2;
					}
					
					$this->ROLLBACK_ADD_TRIGGER('delete', $table_path);
				}

				if (!is_writable($table_path)) {
					$this->_add_error(sprintf('テーブルファイルに書き込みができません [%s]', $table_path));
					break 2;
					
				}

				// ファイルの更新時間を確認
				#touch($table_path);
				if (!empty($this->_TABLE_DATA[$table_path]['mtime']) and ($this->_TABLE_DATA[$table_path]['mtime'] != filemtime($table_path))) {
					$this->_add_error(sprintf('テーブルファイルの書き込み前に修正がありました [%s]', $table_path));
					break 2;
				}
				#r($this->_TABLE_DATA[$table_path]['mtime']);


				
				/*
				if (($fp[$table_path] = fopen($table_path, 'r+b')) === false) {
					$this->_add_error(sprintf('テーブルファイルを開くのに失敗しました [%s]', $table_path));
					break 2;
				}
				
				
				// ファイルのロック
				if (!@flock($fp[$table_path], LOCK_EX)) {
					$this->_add_error(sprintf('テーブルファイルのロックに失敗しました [%s]', $fp[$table_path]));
					break 2;
				}
				*/
				
			}
			
			$flag = true;
			break;
		}
		
		if (!$flag) {
			for ($i=0, $i_max=count($table_paths); $i<$i_max; $i++) {
				$table_path = $table_paths[$i];
				
				if (array_key_exists($table_path, $fp) and is_resource($fp[$table_path])) {
					@fclose($fp[$table_path]);
				}
			}
			
			return false;
		}

		// ファイルの書き込み
		/*
		foreach (range(1, $this->get_option('write_retry_max')) as $n) {
				
		}
		*/

		/*
		for ($i=0, $i_max=count($table_paths); $i<$i_max; $i++) {
			$table_path = $table_paths[$i];
			if (!isset($fp[$table_path])) {
				continue;
			}
			
			ftruncate($fp[$table_path], 0);
			rewind($fp[$table_path]);
			
			fwrite($fp[$table_path], "<?php die(); ?>\n");
			#fwrite($fp[$table_path], $this->_TABLE_DATA[$table_path]['primary_no'] . "\n");
			fwrite($fp[$table_path], $this->fputcsv(array('no', 'type')));
			fwrite($fp[$table_path], $this->fputcsv(array($this->_TABLE_DATA[$table_path]['primary_no'], $this->_TABLE_DATA[$table_path]['type'])));

			#fwrite($fp[$table_path], implode("\t", $this->_TABLE_DATA[$table_path]['field']) . "\n");
			fwrite($fp[$table_path], $this->fputcsv($this->_TABLE_DATA[$table_path]['field']));

			for ($o_i=0, $o_max=count($this->_TABLE_DATA[$table_path]['order']); $o_i<$o_max; $o_i++) {
				$data_key = $this->_TABLE_DATA[$table_path]['order'][$o_i];
				
				$data = array($this->_TABLE_DATA[$table_path]['data'][$data_key][0]);
				for ($f_i=1, $f_max=count($this->_TABLE_DATA[$table_path]['field']); $f_i<$f_max; $f_i++) {
					#$data[] = str_replace(array('#', "\x0D\x0A", "\x0D", "\x0A", "\x09"), array('##', '#n', '#n', '#n', '#t'), $this->_TABLE_DATA[$table_path]['data'][$data_key][$f_i]);
					$data[] = $this->_TABLE_DATA[$table_path]['data'][$data_key][$f_i];
				}
				
				#fwrite($fp[$table_path], implode("\t", $data));
				#fwrite($fp[$table_path], "\n");
				fwrite($fp[$table_path], $this->fputcsv($this->_TABLE_DATA[$table_path]['field']));
			}
			
			#@flock($fp[$table_path], LOCK_UN);
			fclose($fp[$table_path]);
			@chmod($table_path, $this->get_option('file_permission'));

			if (filesize($table_path)) {
				unset($fp[$table_path]);
			}
			
		}
		*/

		$table_list = $table_paths;
		foreach (range(1, $this->get_option('write_retry_max')) as $n) {
			foreach (array_values($table_list) as $table_path) {

				$content = array();
				$content[] = "<?php die(); ?>\n";
				$content[] = $this->fputcsv(array('no', 'type'));
				$content[] = $this->fputcsv(array($this->_TABLE_DATA[$table_path]['primary_no'], $this->_TABLE_DATA[$table_path]['type']));
				$content[] = $this->fputcsv($this->_TABLE_DATA[$table_path]['field']);
	
				for ($o_i=0, $o_max=count($this->_TABLE_DATA[$table_path]['order']); $o_i<$o_max; $o_i++) {
					$data_key = $this->_TABLE_DATA[$table_path]['order'][$o_i];
					$data = array($this->_TABLE_DATA[$table_path]['data'][$data_key][0]);
					for ($f_i=1, $f_max=count($this->_TABLE_DATA[$table_path]['field']); $f_i<$f_max; $f_i++) {
						$data[] = $this->_TABLE_DATA[$table_path]['data'][$data_key][$f_i];
					}
					$content[] = $this->fputcsv($data);
				}
				$size = file_put_contents($table_path, $content, LOCK_EX);
				if (!empty($size)) {
					$table_list  = array_values(array_diff($table_list, array($table_path)));
				}
			}
			if (!count($table_list)) {
				break;
			}
			usleep($this->get_option('write_retry_usleep'));
		}
		
		return true;
	}
	
	/**
	 * データのデコード
	 * 
	 * @param array
	 * @return string
	 */
	protected function _decode_data ($m) {
		$replace = array(
			'n' => "\n",
			't' => "\t",
		);
		$odd = strlen($m[1]) % 2;
		$suffix = '';
		
		if ($odd and array_key_exists($m[2], $replace)) {
			$suffix = $replace[$m[2]];
			
		} else {
			$suffix = $m[2];
			
		}
		
		return str_repeat('#', floor(strlen($m[1]) / 2)) . $suffix;
	}

    /**
     * ファイルポインタから行を取得し、CSVフィールドを処理する
	 * 
     * @param resource handle
     * @param int length
     * @param string delimiter
     * @param string enclosure
     * @return mixed
     */
    public function fgetcsv(&$handle, $length = null, $d = ',', $e = '"') {
        $d = preg_quote($d);
        $e = preg_quote($e);
        $_line = "";
        $eof = false;
        while ($eof != true) {
            $_line .= (empty($length) ? fgets($handle) : fgets($handle, $length));
            $itemcnt = preg_match_all('/' . $e . '/', $_line, $dummy);
            if ($itemcnt % 2 == 0) $eof = true;
        }
        $_csv_line = preg_replace('/(?:\r\n|[\r\n])?$/', $d, trim($_line));
        $_csv_pattern = '/(' . $e . '[^' . $e . ']*(?:' . $e . $e . '[^' . $e . ']*)*' . $e . '|[^' . $d . ']*)' . $d . '/';
        preg_match_all($_csv_pattern, $_csv_line, $_csv_matches);
        $_csv_data = $_csv_matches[1];
        for ($_csv_i = 0; $_csv_i < count($_csv_data); $_csv_i++) {
            $_csv_data[$_csv_i] = preg_replace('/^' . $e . '(.*)' . $e . '$/s', '$1', $_csv_data[$_csv_i]);
            $_csv_data[$_csv_i] = str_replace($e . $e, $e, $_csv_data[$_csv_i]);
        }
        return empty($_line) ? false : $_csv_data;
    }	
	
	/**
	 * テーブルの読み込み
	 * 
	 * @param string $table_path
	 * @return bool
	 */
	protected function _read_table ($table_path) {
		// データの確認
		if ($this->_TRANSACTION_STATUS) {
			if (array_key_exists($table_path, $this->_TABLE_DATA) and $this->_TABLE_DATA[$table_path]['trans']) return true;
		} else {
			if (array_key_exists($table_path, $this->_TABLE_DATA)) return true;
		}
		
		// テーブルディレクトリの確認
		$data_dir = dirname($table_path);
		if (!$this->_check_data_dir($data_dir)) {
			return false;
		}
		
		// テーブルファイルの確認
		if (!is_file($table_path)) {
			return $this->_add_error(sprintf('テーブルファイルが存在しません [%s]', $table_path));
		}
		if (!is_readable($table_path)) {
			return $this->_add_error(sprintf('テーブルファイルを読み込みできません [%s]', $table_path));
		}
		
		// テーブルファイルを開く
		if (($fp = @fopen($table_path, 'r')) === false) {
			return $this->_add_error(sprintf('テーブルファイルを読み込みに失敗しました [%s]', $table_path));
		}
		
		// テーブルの設定
		$this->_TABLE_DATA[$table_path] = array(
			'primary_no' => null,
			'field' => null,
			'order' => array(),
			'data'  => array(),
			'cache' => array(),
			'trans' => $this->_TRANSACTION_STATUS,
			'mtime' => filemtime($table_path),
			'type' => 'list',
		);
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		$flag = false;
		while (true) {
			// 1行目無視
			fgets($fp);
			
			// 2行目 システムヘッダ
			$csv_head = $this->fgetcsv($fp);
			if (empty($csv_head) or (count($csv_head) == 1 and $csv_head[0] == '')) {
				break;
			}
			
			// 3行目 システムデータ
			$csv_col =  $this->fgetcsv($fp);
			if (empty($csv_col) or (count($csv_col) == 1 and $csv_col[0] == '')) {
				break;
			}
			$csv_hash = array();
			foreach ($csv_head as $csv_idx => $csv_key) {
				$csv_hash[$csv_key] = isset($csv_col[$csv_idx]) ? $csv_col[$csv_idx] : '';
			}

			$primary_str = $csv_hash['no'];
			if (strcmp('0', $primary_str) !== 0 and !$this->check_primary_no($primary_str)) break;
			$primary_no = floatval($primary_str);

			$data_type = in_array($csv_hash['type'], array('list', 'hash')) ? $csv_hash['type'] : 'list';

			// 4行目 データヘッダ
			$csv_col =  $this->fgetcsv($fp);
			if (empty($csv_col) or (count($csv_col) == 1 and $csv_col[0] == '')) {
				break;
			}

			/*
			//
			$field_str = trim(fgets($fp));
			if (!strlen($field_str)) break;
			$field_array = explode("\t", $field_str);
			
			$field = array();
			for ($i=0, $i_max=count($field_array); $i<$i_max; $i++) {
				$f = $field_array[$i];
				
				if (is_string($f) and strlen($f) and strpos($f, " ") === false) {
					$field[] = $f;
				}
			}
			
			if (count($field_array) != count($field)) break;
			*/
			
			$field = array();
			for ($i=0, $i_max=count($csv_col); $i<$i_max; $i++) {
				$f = $csv_col[$i];

				if ($this->_check_field_name($f)) {
					$field[] = $f;
				}
	
			}
			
			if (count($csv_col) != count($field)) {
				break;
			}
			
			$table_data['primary_no'] = $primary_no;
			$table_data['field'] = $field;
			$table_data['type'] = $data_type;
			
			while (!feof($fp)) {
				#$data = explode("\t", rtrim(fgets($fp)));
				$data =  $this->fgetcsv($fp);

				if (!$this->check_primary_no($data[0])) continue;
				
				$p_str = $data[0];
				$p_no =floatval($data[0]);
				
				if ($p_no > $table_data['primary_no']) $table_data['primary_no'] = $p_no; 
				
				$table_data['order'][] = $p_str;
				
				$table_data['data'][$p_str] = array($p_str);
				for ($i=1, $i_max=count($field); $i<$i_max; $i++ ) {
					#$table_data['data'][$p_str][$i] = str_replace(array('{*+-BR-+*}', '{*+-TAB-+*}'), array("\n", "\t"), (isset($data[$i]) ? $data[$i] : ''));
					#$table_data['data'][$p_str][$i] = isset($data[$i]) ? (strpos($data[$i], '#') !== false ? preg_replace_callback('/(#+)([n|t]?)/', array($this, '_decode_data'), $data[$i]) : $data[$i]) : '';
					#$table_data['data'][$p_str][$i] = isset($data[$i]) ? (strpos($data[$i], '#') !== false ? preg_replace_callback('/(#+)([n|t]?)/', array($this, '_decode_data'), $data[$i]) : $data[$i]) : '';
					$table_data['data'][$p_str][$i] = isset($data[$i]) ? (strpos($data[$i], '\\') !== false ? preg_replace_callback('/(\\+)([n|t]?)/', array($this, '_decode_data'), $data[$i]) : $data[$i]) : '';
				}
				
			}
			
			$flag = true;
			break;
		}
		
		fclose($fp);
		
		if (!$flag) {
			unset($this->_TABLE_DATA[$table_path]);
			return $this->_add_error(sprintf('テーブルファイルの構造が正しくありません [%s]', $table_path));
		}
		
		return true;
		
	}
	
	/**
	 * テーブルデータの取得
	 * 
	 * @param string $table_path
	 * @param string $no
	 * @param array $data
	 * @param mixed $callback
	 * @return bool
	 */
	protected function _get_table_data ($table_path, $no, &$data, $callback=null) {
		if (!isset($this->_TABLE_DATA[$table_path]['data'][$no])) return false;
		
		if (!is_array($data)) $data = array();
		
		for ($i=0, $i_max=count($this->_TABLE_DATA[$table_path]['field']); $i<$i_max; $i++) {
			$data[$this->_TABLE_DATA[$table_path]['field'][$i]] = $this->_TABLE_DATA[$table_path]['data'][$no][$i];
		}
		
		if (!is_null($callback) and !array_key_exists($no, $this->_TABLE_DATA[$table_path]['cache'])) {
			$this->_TABLE_DATA[$table_path]['cache'][$no] = array();
			@call_user_func_array($callback, array($data, &$this->_TABLE_DATA[$table_path]['cache'][$no]));
			
			if (!is_array($this->_TABLE_DATA[$table_path]['cache'][$no])) {
				unset($this->_TABLE_DATA[$table_path]['cache'][$no]);
				
			} else {
				$data = $data + $this->_TABLE_DATA[$table_path]['cache'][$no];
				
			}
			
		}
		
		return true;
	}
	
	/**
	 * テーブルデータの取得 拡張
	 *
	 * @param string $table_path
	 * @param int $no
	 * @param array $data
	 * @param mixed $callback
	 * @return bool
	 */
	protected function _get_table_data_ex ($table_path, $no, &$data, $callback=null) {
		if (!isset($this->_TABLE_DATA[$table_path]['data'][$no])) return false;
		
		if (!is_array($data)) $data = array();
		
		if (isset($this->_TABLE_DATA[$table_path]['cache'][$no])) {
			$data = $data + $this->_TABLE_DATA[$table_path]['cache'][$no];
		}
		
		for ($i=0, $i_max=count($this->_TABLE_DATA[$table_path]['field']); $i<$i_max; $i++) {
			$data[$this->_TABLE_DATA[$table_path]['field'][$i]] = $this->_TABLE_DATA[$table_path]['data'][$no][$i];
		}
		
		
		
		if (!is_null($callback) and !array_key_exists($no, $this->_TABLE_DATA[$table_path]['cache'])) {
			$this->_TABLE_DATA[$table_path]['cache'][$no] = array();
			@call_user_func_array($callback, array($data, &$this->_TABLE_DATA[$table_path]['cache'][$no]));
			
			if (!is_array($this->_TABLE_DATA[$table_path]['cache'][$no])) {
				unset($this->_TABLE_DATA[$table_path]['cache'][$no]);
				
			} else {
				$data = $data + $this->_TABLE_DATA[$table_path]['cache'][$no];
				
			}
			
		}
		
		return true;
	}
	
	/**
	 * FETCH
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @param int $no
	 * @param array $data
	 * @param mixed $callback
	 * @return bool
	 */
	public function FETCH ($dir, $table_name, $no, &$data, $callback=null) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルデータの取得
		if (!$this->_get_table_data($table_path, $no, $data, $callback)) return false;
		
		return true;
	}
	
	/**
	 * FETCH EX
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @param int $no
	 * @param array $data
	 * @param mixed $callback
	 * @return bool
	 */
	public function FETCH_EX ($dir, $table_name, $no, &$data, $callback=null) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルデータの取得
		if (!$this->_get_table_data_ex($table_path, $no, $data, $callback)) return false;
		
		return true;
	}
	
	
	/**
	 * INSERT
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param array $hash
	 * @param string $type push or unshift
	 * @return mixed
	 */
	public function INSERT ($dir, $table_name, $hash, $type='push') {
		// ハッシュの確認
		if (!is_array($hash)) return $this->_add_error('連想配列を指定してください');

		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブル 読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];

		// データの設定
		$primary_no = strval(++$table_data['primary_no']);
		
		$data = array($primary_no);
		
		for ($i=1, $i_max=count($table_data['field']); $i<$i_max; $i++) {
			$data[$i] = isset($hash[$table_data['field'][$i]]) ? strval($hash[$table_data['field'][$i]]) : '';
		}

		// 追加
		if (is_string($type)) $type = strtolower($type);
		$type = in_array($type, array('unshift', 'push')) ? $type : 'push';
		
		
		if ($type == 'push') {
			array_push($table_data['order'], $primary_no);
		} else {
			array_unshift($table_data['order'], $primary_no);
		}
		
		// データの更新
		$table_data['data'][$primary_no] = $data;
		
		$this->_TABLE_UPDATE[$table_path] = true;
		
		return $primary_no;
	}
	
	/**
	 * SELECT
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param mixed $where
	 * @param mixed $order
	 * @param mixed $fetch
	 * @return mixed
	 */
	public function SELECT ($dir, $table_name, $where=null, $order=null, $fetch=null) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		// 変数の設定
		$where_cb = null;
		$where_no = array();
		$fetch_cb = null;
		$order_cb =null;
		
		
		// 条件の確認
		if ($this->check_primary_no($where)) {
			$where_cb = array(strval($where));
			
		} else if (is_callable($where)) {
			$where_cb = $where;
			
		} /*else if (is_string($where)) {
			$where_cb = @create_function('$data','return ('.$where.') ? true : false;');
			
		} */else if ($this->is_array($where)) {
			$where_cb = array();
			for ($i=0, $i_max=count($where); $i<$i_max; $i++) {
				if ($this->check_primary_no($where[$i])) $where_cb[] = strval($where[$i]);
			}
			
		}
		
		if (is_callable($fetch)) {
			$fetch_cb = $fetch;
		}
		
		if (is_callable($order)) {
			$order_cb = $order;
		} /*else if (is_string($order)) {
			$order_cb = @create_function('$data','return ('.$order.') ? true : false;');
			
		}*/
		
		// whereの実行
		if (is_callable($where_cb)) {
			for ($i=0, $i_max=count($table_data['order']); $i<$i_max; $i++) {
				$h = array();
				if (!$this->_get_table_data($table_path, $table_data['order'][$i], $h, $fetch_cb)) continue;
				
				#if (call_user_func($where_cb, $h)) $where_no[] = $table_data['order'][$i];
				if (call_user_func_array($where_cb, array(&$h))) $where_no[] = $table_data['order'][$i];
			}
			
		} else if (is_array($where_cb)) {
			for ($i=0, $i_max=count($where_cb); $i<$i_max; $i++) {
				if (array_key_exists($where_cb[$i], $table_data['data'])) $where_no[] = $where_cb[$i];
			}
			
		} else if (is_null($where)) {
			$where_no = $table_data['order'];
			
		}
		
		// orderの実行
		if (is_callable($order_cb) and count($where_no)) {
			$order_data = array();
			for ($i=0, $i_max=count($where_no); $i<$i_max; $i++) {
				$h = array();
				if (!$this->_get_table_data($table_path, $where_no[$i], $h, $fetch_cb)) continue;
				
				$order_data[$where_no[$i]] = $h;
				$order_data[$where_no[$i]]['__order__'] = $i;
				
			}
			
			uasort($order_data, $order_cb);
			$where_no = array_keys($order_data);
			
		}
		
		return $where_no;
	}
	
	/**
	 * UPDATE
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param mixed $where
	 * @param array $hash
	 * @param mixed $fetch
	 * @return mixed
	 */
	public function UPDATE ($dir, $table_name, $where, $hash, $fetch=null) {
		// ハッシュの確認
		if (!is_array($hash)) return $this->_add_error('連想配列を指定してください');
		
		// SELECTの取得
		$nos = $this->SELECT($dir, $table_name, $where, null, $fetch);
		if (!is_array($nos)) return false; 
		if (!count($nos)) return array();
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		//
		$data_hash = array();
		for ($i=1, $i_max=count($table_data['field']); $i<$i_max; $i++) {
			if (isset($hash[$table_data['field'][$i]])) {
				$data_hash[$i] = strval($hash[$table_data['field'][$i]]);
			}
		}
		$data_keys = array_keys($data_hash);
		$data_keys_max = count($data_keys);
		
		// テーブルデータの更新
		for ($n_i=0, $n_max=count($nos); $n_i<$n_max; $n_i++) {
			 for ($d_i=0; $d_i<$data_keys_max; $d_i++) {
			 	$table_data['data'][$nos[$n_i]][$data_keys[$d_i]] = $data_hash[$data_keys[$d_i]];
			 }
			 unset($table_data['cache'][$nos[$n_i]]);
		}
		
		$this->_TABLE_UPDATE[$table_path] = true;
		
		
		return $nos;
	}
	
	/**
	 * NOの存在の確認
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param string $no
	 * @return bool
	 */
	public function NO_EXISTS ($dir, $table_name, $no) {
		if (!$this->check_primary_no($no)) return $this->_add_error('NOを正しく認識できません');
		$no = strval($no);
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		return array_key_exists($no, $this->_TABLE_DATA[$table_path]['data']) ? true : false;
	}
	
	/**
	 * NOの取得
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @return int
	 */
	public function NO_GET ($dir, $table_name) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		return strval($table_data['primary_no']);
	}
	
	/**
	 * NOのローテーション
	 *
	 * @param int $no
	 * @param string $format
	 * @return string
	 */
	public function no_rotation ($no, $format="%04d") {
		if (!$this->check_primary_no($no)) {
			return $this->_add_error('NOを正しく認識できません');
		}
		return sprintf($format, ($no ? ceil($no / 100) : 0));
	}

	/**
	 * INSERT_EX
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param string $no
	 * @param array $hash
	 * @param string $type push or unshift
	 * @param mixed $fetch
	 * @return bool
	 */
	public function INSERT_EX ($dir, $table_name, $no, $hash, $type='push', $fetch=null) {
		//
		if (!$this->check_primary_no($no)) return $this->_add_error('NOを正しく認識できません');
		
		// ハッシュの確認
		if (!is_array($hash)) return $this->_add_error('連想配列を指定してください');
		
		//
		if ($this->NO_EXISTS($dir, $table_name, $no)) {
			$r = $this->UPDATE($dir, $table_name, $no,  $hash, $fetch);
			return ($r === false) ? false : true;
		}
		
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブル 読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		// データの設定
		$primary_no = floatval($no);
		if ($table_data['primary_no'] < $primary_no) $table_data['primary_no'] = $primary_no;
		$primary_no = strval($primary_no);
		
		$data = array($primary_no);
		
		for ($i=1, $i_max=count($table_data['field']); $i<$i_max; $i++) {
			$data[$i] = isset($hash[$table_data['field'][$i]]) ? strval($hash[$table_data['field'][$i]]) : '';
		}
		
		// 追加
		if (is_string($type)) $type = strtolower($type);
		$type = in_array($type, array('unshift', 'push')) ? $type : 'push';
		
		if ($type == 'push') {
			array_push($table_data['order'], $primary_no);
		} else {
			array_unshift($table_data['order'], $primary_no);
		}
		
		// データの更新
		$table_data['data'][$primary_no] = $data;
		
		$this->_TABLE_UPDATE[$table_path] = true;
		
		return true;
	}
	
	/**
	 * DELETE
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param mixed $where 
	 * @param callback $fetch
	 * @return mixed
	 */
	public function DELETE ($dir, $table_name, $where, $fetch=null) {
		// SELECTの取得
		$nos = $this->SELECT($dir, $table_name, $where, null, $fetch);
		if (!is_array($nos)) return false; 
		if (!count($nos)) return array();
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		// テーブルデータの更新
		$d_nos = array();
		for ($n_i=0, $n_max=count($nos); $n_i<$n_max; $n_i++) {
			unset($table_data['data'][$nos[$n_i]]);
			unset($table_data['cache'][$nos[$n_i]]);
			$d_nos[$nos[$n_i]] = true;
		}
		
		for ($o_i=0, $o_max=count($table_data['order']); $o_i<$o_max; $o_i++) {
			if (array_key_exists($table_data['order'][$o_i], $d_nos)) unset($table_data['order'][$o_i]);
		}
		
		$table_data['order'] = array_values($table_data['order']);
		
		$this->_TABLE_UPDATE[$table_path] = true;
		
		return $nos;
	}
	
	/**
	 * SORT
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @param string $type 'top', 'last', 'up', 'down'
	 * @param mixed $where
	 * @param mixed $fetch
	 * @return bool
	 */
	public function SORT ($dir, $table_name, $type, $source_no, $where=null, $fetch=null) {
		if (!in_array($type, array('up','down','top','last'))) return $this->_add_error('利用できないタイプです');
		
		// SELECTの取得
		$select_nos = $this->SELECT($dir, $table_name, $where, null, $fetch);
		if (!is_array($select_nos) or !count($select_nos)) return false;
		
		// 確認
		$select_chk = array_flip($select_nos);
		if (!isset($select_chk[$source_no])) {
			return $this->_add_error('利用できないNOです');
		}
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		
		//
		$order_list = array();
		for ($i=0, $i_max=count($table_data['order']); $i<$i_max; $i++) {
			if (isset($select_chk[$table_data['order'][$i]])) $order_list[] =& $table_data['order'][$i];
		}
		unset($select_chk);
		
		$source_idx = array_search($source_no, $order_list);
		$source_no = $order_list[$source_idx];
		$order_cnt = count($order_list);
		
		#vd($order_list, $source_no, $source_idx);
		
		//
		if ($type == 'up' and $source_idx > 0) {
			$tmp_no = $order_list[$source_idx - 1];
			$order_list[$source_idx - 1] = $source_no;
			$order_list[$source_idx] = $tmp_no;
			
		} if ($type == 'down' and $source_idx < ($order_cnt - 1)) {
			$tmp_no = $order_list[$source_idx + 1];
			$order_list[$source_idx + 1] = $source_no;
			$order_list[$source_idx] = $tmp_no;
			
		} else if (($type == 'top' and $source_idx > 0) or ($type == 'last' and $source_idx < ($order_cnt - 1))) {
			$tmp_order_list = array();
			for ($i=0; $i<$order_cnt; $i++) {
				if ($i != $source_idx) $tmp_order_list[] = $order_list[$i];
			}
			
			if ($type == 'top') {
				array_unshift($tmp_order_list, $source_no);
			} else {
				array_push($tmp_order_list, $source_no);
			}
			
			for ($i=0; $i<$order_cnt; $i++) {
				$order_list[$i] = $tmp_order_list[$i];
			}
			
			#vd($tmp_order_list);
			#vd('XXXX');
		}
		
		$this->_TABLE_UPDATE[$table_path] = true;
		return true;
	}

	/**
	 * ソート 拡張
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @param string $type
	 * @param array $source_nos
	 * @param int $dest_no
	 * @param mixed $where
	 * @param mixed $fetch
	 * @return bool
	 */
	public function SORT_EX ($dir, $table_name, $type, $source_nos, $dest_no, $where=null, $fetch=null) {
		if (!in_array($type, array('up','down'))) return $this->_add_error('利用できないタイプです');
		
		// SELECTの取得
		$select_nos = $this->SELECT($dir, $table_name, $where, null, $fetch);
		if (!is_array($select_nos) or !count($select_nos)) return false;
		
		// 確認
		$select_cnt = count($select_nos);
		$select_chk = array_flip($select_nos);
		$source_cnt = count($source_nos);
		$source_chk = array_flip($source_nos);
		$source_nos = array();
		for ($i=0; $i<$select_cnt; $i++) {
			if (isset($source_chk[$select_nos[$i]])) $source_nos[] = $select_nos[$i];
		}
		if (count($source_nos) != $source_cnt) {
			return $this->_add_error('SOURCE NOSが正しくありません');
		}
		$source_chk = array_flip($source_nos);
		if (isset($source_chk[$dest_no])) {
			return $this->_add_error('SOURCE NOSにDEST NOが存在してます');
		}
		$dest_idx = array_search($dest_no, $select_nos);
		if ($dest_idx === false) {
			return $this->_add_error('利用できないDEST NOです');
		}
		$dest_no = $select_nos[$dest_idx];
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		//
		$order_list = array();
		for ($i=0, $i_max=count($table_data['order']); $i<$i_max; $i++) {
			if (isset($select_chk[$table_data['order'][$i]])) $order_list[] =& $table_data['order'][$i];
		}
		unset($select_chk);
		$order_cnt = count($order_list);
		
		
		//
		$tmp_order_list = array();
		for ($i=0; $i<$order_cnt; $i++) {
			if (isset($source_chk[$order_list[$i]])) {
				continue;
			} else if ($order_list[$i] == $dest_no) {
				
				if ($type == 'up') {
					$tmp_order_list = array_merge($tmp_order_list, $source_nos);
				}
				
				$tmp_order_list[] = $order_list[$i];
				
				if ($type == 'down') {
					$tmp_order_list = array_merge($tmp_order_list, $source_nos);
				}
				
			} else {
				$tmp_order_list[] = $order_list[$i];
			}
		}
		
		if ($order_cnt != count($tmp_order_list)) {
			return $this->_add_error('並び替えに失敗しました');
		}
		
		for ($i=0; $i<$order_cnt; $i++) {
			$order_list[$i] = $tmp_order_list[$i];
		}
		
		$this->_TABLE_UPDATE[$table_path] = true;
		return true;
		
		#vd($order_list);
		/*
		// 
		if (!in_array($source_no, $select_nos)) {
			return $this->_add_error('利用できないNOです');
		}
		*/
		#vd($dir, $table_name, $type, $source_nos, $dest_no);
	}
	
	/**
	 * ソート コールバック
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @param mixed $callback
	 * @param mixed $where
	 * @param mixed $fetch
	 * @return bool
	 */
	public function SORT_CB ($dir, $table_name, $callback, $where=null, $fetch=null) {
		if (!is_callable($callback)) return $this->_add_error('利用できないコールバックです');
		
		// SELECTの取得
		$select_nos = $this->SELECT($dir, $table_name, $where, null, $fetch);
		if (!is_array($select_nos) or !count($select_nos)) return false;
		
		// CALLBACKの取得
		$order_nos = $this->SELECT($dir, $table_name, $where, $callback, $fetch);
		if (!is_array($select_nos) or !count($select_nos)) return false;
		
		// 確認
		if (count($select_nos) != count($order_nos)) return $this->_add_error('並び替えに失敗しました');
		
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		// 
		$select_chk = array_flip($select_nos);
		$order_list = array();
		for ($i=0, $i_max=count($table_data['order']); $i<$i_max; $i++) {
			if (isset($select_chk[$table_data['order'][$i]])) $order_list[] =& $table_data['order'][$i];
		}
		unset($select_chk);
		
		for ($i=0, $i_max=count($order_list); $i<$i_max; $i++) {
			$order_list[$i] = strval($order_nos[$i]);
		}
		
		$this->_TABLE_UPDATE[$table_path] = true;
		
		return true;
		
		#vd($order_list);
		#vd($table_data, $select_nos, $order_nos);
		
		
	}
	
	
	/**
	 * テーブル フィールドの変更
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @param array $field
	 * @return bool
	 */	
	public function TABLE_ALTER_FIELD ($dir, $table_name, $field) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブル 読み込み
		if (!$this->_read_table($table_path)) return false;
		
		
		// 構造の確認
		if (!$this->is_array($field)) {
			return $this->_add_error(sprintf('フィールドを正しく認識できません [%s]', $table_path));
		}
		
		$field_ = array();
		for ($i=0, $i_max=count($field); $i<$i_max; $i++) {
			$f = $field[$i];
			
			if (is_string($f) and strlen($f) and strpos($f, " ") === false and strpos($f, "\n") === false and strpos($f, "\r") === false and strpos($f, "\t") === false) {
				$field_[] = $f;
			}
			
		}
		
		if (count($field) != count($field_)) {
			return $this->_add_error(sprintf('フィールドを設定が正しくありません [%s]', $table_path));
		}
		
		
		// テーブルデータの取得
		$table_data =& $this->_TABLE_DATA[$table_path];
		
		
		// 構造の比較
		if ($table_data['field'] !== $field_) {
			$f_max = count($field_);
			
			for ($o_i=0, $o_max=count($table_data['order']); $o_i<$o_max; $o_i++) {
				$no = $table_data['order'][$o_i];
				$hash = array();
				if (!$this->_get_table_data($table_path, $no, $hash)) continue;
				
				$data = array($no);
				for ($f_i=1; $f_i<$f_max; $f_i++) {
					$data[$f_i] = isset($hash[$field_[$f_i]]) ? $hash[$field_[$f_i]] : '';
				}
				
				$table_data['data'][$no] = $data;
				unset($table_data['cache'][$no]);
			}
			
			$table_data['field'] = $field_;
			$this->_TABLE_UPDATE[$table_path] = true;
		}
		
		return true;
		
	}
	
	/**
	 * テーブル 名前の変更
	 *
	 * @param string $source_dir
	 * @param string $source_table_name
	 * @param string $dest_dir
	 * @param string $dest_table_name
	 * @param boolean $overwrite
	 * @return bool
	 */
	public function TABLE_RENAME ($source_dir, $source_table_name, $dest_dir, $dest_table_name, $overwrite=true) {
		if (!$this->TABLE_COPY($source_dir, $source_table_name, $dest_dir, $dest_table_name, $overwrite)) return false;
		return $this->TABLE_DROP($source_dir, $source_table_name);
	}
	
	/**
	 * テーブルのコピー
	 *
	 * @param string $source_dir
	 * @param string $source_table_name
	 * @param string $dest_dir
	 * @param string $dest_table_name
	 * @param bool $overwrite
	 * @return bool
	 */
	public function TABLE_COPY ($source_dir, $source_table_name, $dest_dir, $dest_table_name, $overwrite=true) {
		// テーブル名の確認
		if (!$this->_check_table_name($dest_table_name)) return false;
		
		// テーブルパスの取得
		$dest_table_path = '';
		if (!$this->get_table_path($dest_dir, $dest_table_name, $dest_table_path)) return false;
		
		// テーブルパスの取得
		$source_table_path = '';
		if (!$this->get_table_path($source_dir, $source_table_name, $source_table_path)) return false;
		
		// 上書きの確認
		if (!$overwrite) {
			if (is_file($dest_table_path) or $this->_TABLE_UPDATE[$dest_table_path]) {
				return $this->_add_error(sprintf('コピー先のテーブルはすでに存在しています [%s]', $dest_table_path));
			}
		}
		
		// テーブル 読み込み
		if (!$this->_read_table($source_table_path)) return false;
		
		$this->_TABLE_DATA[$dest_table_path] = $this->_TABLE_DATA[$source_table_path];
		$this->_TABLE_UPDATE[$dest_table_path] = true;
		
		return true;
	}
	 
	/**
	 * テーブル 削除
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @return bool
	 */
	public function TABLE_DROP ($dir, $table_name) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		if (is_file($table_path) or isset($this->_TABLE_UPDATE[$table_path])) {
			$this->_TABLE_DROP[$table_path] = true;
		}
		return true;
	}
	
	/**
	 * テーブル クリア
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @return bool
	 */
	public function TABLE_CLEAR ($dir, $table_name) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
	
		if (isset($this->_TABLE_DATA[$table_path])) {
				
			$this->_TABLE_DATA[$table_path]['primary_no'] = floatval(0);
			$this->_TABLE_DATA[$table_path]['order'] = array();
			$this->_TABLE_DATA[$table_path]['data'] = array();
			$this->_TABLE_DATA[$table_path]['cache'] = array();
			$this->_TABLE_DATA[$table_path]['trans'] = true;
				
			$this->_TABLE_UPDATE[$table_path] = true;
				
			return true;
		}
		return false;
	}
	
	
	/**
	 * データ クリア
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @return bool
	 */
	public function DATA_CLEAR ($dir, $table_name) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		if (isset($this->_TABLE_DATA[$table_path])) {
			unset($this->_TABLE_DATA[$table_path]);
		}
		
		if (isset($this->_TABLE_UPDATE[$table_path])) {
			unset($this->_TABLE_UPDATE[$table_path]);
		}
		/*
		if (isset($this->_TABLE_DROP[$table_path])) {
			unset($this->_TABLE_DROP[$table_path]);
		}
		*/
		return true;
	}
	
	
	/**
	 * データ カウント
	 * 
	 * @param string $dir
	 * @param string $table_name
	 * @return int
	 */
	public function DATA_COUNT ($dir, $table_name) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブル 読み込み
		if (!$this->_read_table($table_path)) return false;
		
		return count($this->_TABLE_DATA[$table_path]['order']);
	}
	
	/**
	 * ディレクトリの作成
	 *
	 * @param string $root_dir
	 * @param string $target_dir
	 * @param int $mode
	 * @return void
	 */
	protected function make_dir_deep ($root_dir, $target_dir, $mode=null) {
		if (is_null($mode)) $mode = $this->get_option('dir_permission');
		
		$root_dir = $this->_get_real_path($root_dir);
		if ($root_dir === false) {
			trigger_error(sprintf('TextDB::make_dir_deep ERROR: Cannot use root dir [%s]', $root_dir), E_USER_ERROR);
			return false;
		}
		if (strpos($target_dir, $root_dir) !== 0) {
			trigger_error(sprintf('TextDB::make_dir_deep ERROR: Cannot use target dir [%s]', $target_dir), E_USER_ERROR);
			return false;
		}
		
		if (is_dir($target_dir)) {
			if (!is_writable($target_dir)) {
				if (@chmod($target_dir, $mode)) {
					trigger_error(sprintf('TextDB::make_dir_deep ERROR: Cannot change permission target dir [%s]', $target_dir), E_USER_ERROR);
					return false;
				}
			}
			return true;
		}
		
		$tree_path = strlen($root_dir) == strlen($target_dir) ? '' : substr($target_dir, strlen($root_dir));
		
		$current_dir = $root_dir;
		if (!is_writable($current_dir)) {
			trigger_error(sprintf('TextDB::make_dir_deep ERROR: Cannot use dir permission [%s]', $target_dir), E_USER_ERROR);
			return false;
		}
		
		foreach (explode(DIRECTORY_SEPARATOR, $tree_path) as $dir) {
			if (!strlen($dir)) continue;
			
			$current_dir .= $dir . DIRECTORY_SEPARATOR;
			
			if (!is_dir($current_dir)) {
				if (!mkdir($current_dir)) {
					trigger_error(sprintf('TextDB::make_dir_deep ERROR: Cannot make dir [%s]', $current_dir), E_USER_ERROR);
					return false;
				}
				
				chmod($current_dir, $mode);
				
				$this->ROLLBACK_ADD_TRIGGER('delete', $current_dir);
			}
			
			if (!is_writable($current_dir)) {
				trigger_error(sprintf('TextDB::make_dir_deep ERROR: Cannot write dir [%s]', $current_dir), E_USER_ERROR);
				return false;
			}
			
		}
		
		return true;
	}

	/**
	 * フラグ名の確認
	 * 
	 * @param string $name
	 * @return bool
	 */
	protected function _flag_check_name ($name) {
		$flag = false;
		do {
			if (!is_string($name)) {
				break;
			}
			if (!strlen($name)) {
				break;
			}
			if (!preg_match($this->get_option('flag_name_pattan'), $name)) {
				break;
			}
			if ($this->_str_endswith(strtolower($name), strtolower($this->get_option('table_extension')))) {
				break;
			}
			
			$flag = true;
		} while (false);

		if (!$flag) {
			return $this->_add_error(sprintf('フラグ名に利用できない文字を含んでいます [%s]', $name));
		} else {
			return true;
		}
	}

	/**
	 * フラグ クリアー
	 * 
	 * @param string $target
	 * @param bool $root
	 * @return bool
	 */
	protected function _flag_clear ($target, $root=false) {
		if (!file_exists($target)) return true;
		
		if (is_dir($target)) {
			if ($fh = @opendir($target)) {
				while (($name = readdir($fh)) !== false) {
					if (in_array($name, array('.', '..'))) continue;
					
					$path = $target . DIRECTORY_SEPARATOR . $name;
					if (is_file($path)) {
						$info = pathinfo($path);
						if ('.' . $info['extension'] == $this->get_option('flag_extension')) {
							@unlink($path);
						}
					} else if (is_dir($path)) {
						$this->_flag_clear($path);
					}
					
				}
				
				closedir($fh);
			}
			
			if (!$root) {
				$file_cnt = 0;
				if ($fh = @opendir($target)) {
					while (($name = readdir($fh)) !== false) {
						if (in_array($name, array('.', '..'))) continue;
						
						$file_cnt++;
					}
					closedir($fh);
				}
				if (!$file_cnt) {
					@rmdir($target);
				}
			}
			
		}
		
		return true;
	}
	
	/**
	 * フラグ クリアー
	 *
	 * @param string $dir
	 * @return bool
	 */
	public function FLAG_CLEAR ($dir) {
		$dir = $this->_get_virtual_dir_path($dir);
		
		if (strpos($dir, $this->_ROOT_DIR) !== 0) {
			return $this->_add_error(sprintf('ルートディレクトリ以下以外のディレクトリです [%s : %s]', __FUNCTION__, $dir));
		}
		
		$this->_FLAG_ACTION[] = array(
			'type' => 'clear',
			'source' => $dir,
		);
		
		return true;
	}
	
	/**
	 * フラグ 追加
	 *
	 * @param string $dir
	 * @param string $flag_name
	 * @return void
	 */
	public function FLAG_ADD ($dir, $flag_name) {
		$dir = $this->_get_virtual_dir_path($dir);
		
		if (strpos($dir, $this->_ROOT_DIR) !== 0) {
			return $this->_add_error(sprintf('ルートディレクトリ以下以外のディレクトリです [%s]', $dir));
		}

		if (!$this->_flag_check_name($flag_name)) {
			return false;
		}
		
		$this->_FLAG_ACTION[] = array(
			'type' => 'add',
			'source' => $dir . $flag_name . $this->get_option('flag_extension'),
		);
		
		return true;
	}
	
	/**
	 * フラグ 削除
	 *
	 * @param string $dir
	 * @param string $flag_name
	 * @return void
	 */
	public function FLAG_DELETE ($dir, $flag_name) {
		if (strpos($dir, $this->_ROOT_DIR) !== 0) {
			return $this->_add_error(sprintf('ルートディレクトリ以下以外のディレクトリです [%s]', $dir));
		}
		
		if (!$this->_flag_check_name($flag_name)) {
			return false;
		}
		
		$this->_FLAG_ACTION[] = array(
			'type' => 'delete',
			'source' => $dir . $flag_name . $this->get_option('flag_extension'),
		);
		
		return true;
	}
	
	/**
	 * フラグ 確認
	 *
	 * @param string $dir
	 * @param string $flag_name
	 * @return void
	 */
	public function FLAG_EXISTS ($dir, $flag_name) {
		return file_exists($dir . $flag_name . $this->get_option('flag_extension'));
	}

	/**
	 * ハッシュテーブル 作成
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @return bool
	 */
	public function HASH_CREATE ($dir, $table_name) {
		$field = array('no', 'key', 'val');
		return $this->TABLE_CREATE($dir, $table_name, $field, 'hash');
	}

	/**
	 * ハッシュテーブル 作成
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @return bool
	 */
	protected function _hash_check_table ($dir, $table_name) {
		// テーブルパスの取得
		$table_path = '';
		if (!$this->get_table_path($dir, $table_name, $table_path)) return false;
		
		// テーブルの読み込み
		if (!$this->_read_table($table_path)) return false;

		// タイプの確認
		if ($this->_TABLE_DATA[$table_path]['type'] != 'hash') {
			return $this->_add_error(sprintf('テーブルのタイプがハッシュではありません [%s]', $table_name));
		}

		return false;
	}

	/**
	 * ハッシュテーブル 更新
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @param array $hash
	 * @param bool $clear
	 * @return mixed
	 */
	public function HASH_UPDATE ($dir, $table_name, $hash, $clear=false) {
		// ハッシュの確認
		if (!is_array($hash)) {
			return $this->_add_error('連想配列を指定してください');
		}

		// タイプの確認
		if ($this->_hash_check_table($dir, $table_name)) {
			return false;
		}

		// データのクリア
		if ($clear) {
			$this->DATA_CLEAR($dir, $table_name);
		}

		// 連想配列の更新
		$key_list = array_keys($hash);
		$ret_count = 0;
		$nos = $this->SELECT($dir, $table_name);
		foreach ($nos as $no) {
			$data = array();
			if (!$this->FETCH($dir, $table_name, $no, $data)) {
				continue;
			}
			if (in_array($data['key'], $key_list)) {
				$ret = $this->UPDATE($dir, $table_name, $no, array('key'=>$data['key'], 'val'=>$hash[$data['key']]));
				if (empty($ret)) {
					return false;
				} else {
					$ret_count += count($ret);
				}
				$key_list = array_values(array_diff($key_list, array($data['key'])));
			}
		}
		
		// 連想配列の追加
		foreach ($key_list as $key) {
			$ret = $this->INSERT($dir, $table_name, array('key'=>$key, 'val'=>$hash[$key]));
			if (empty($ret)) {
				return false;
			} else {
				$ret_count++;
			}
		}

		return $ret_count;
	}

	/**
	 * ハッシュテーブル 取得
	 *
	 * @param string $dir
	 * @param string $table_name
	 * @param array $data
	 * @param mixed $callback
	 * @return bool
	 */
	public function HASH_FETCH ($dir, $table_name, &$data, $callback=null) {
		// ハッシュの確認
		if (!is_array($data)) {
			return $this->_add_error('連想配列を指定してください');
		}

		// タイプの確認
		if ($this->_hash_check_table($dir, $table_name)) {
			return false;
		}

		$nos = $this->SELECT($dir, $table_name);
		foreach ($nos as $no) {
			$hash = array();
			if (!$this->FETCH($dir, $table_name, $no, $hash, $callback)) {
				continue;
			}
			$data[$hash['key']] = $hash['val'];
		}

		return true;
	}




}