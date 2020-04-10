<?php

namespace Jd29;

final class Util
{
    /**
     * リトライ回数
     *
     * @var integer
     */
    static public $retry_count = 10;

    /**
     * リトライマイクロ秒
     *
     * @var integer
     */
    static public $retry_usleep = 200000;

    /**
     * ファイル パーミッション
     * private 0700
     * @var integer
     */
    static public $mode_dir = 0755;

    /**
     * ディレクトリ パーミッション
     * private 0600
     * @var integer
     */
    static public $mode_file = 0666;


    static public function test()
    {
        #echo "Hello World!";
        r(static::$retry_count);
        r(static::$retry_usleep);
        r(static::$mode_dir);
        r(static::$mode_file);
    }

    /**
     * python startsWith
     * @see https://qiita.com/satoshi-nishinaka/items/f15ccbcf8b8f91c1e2dd
     *
     * @param string $haystack
     * @param string $needle
     * @param boolean $case
     * @return boolean
     */
    static public function startsWith($haystack, $needle, $case = false)
    {
        if ($case) {
            return (strcasecmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
        } else {
            return (strcmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
        }
    }

    /**
     * python endsWith
     * @see https://qiita.com/satoshi-nishinaka/items/f15ccbcf8b8f91c1e2dd
     *
     * @param string $haystack
     * @param string $needle
     * @param boolean $case
     * @return boolean
     */
    static public function endsWith($haystack, $needle, $case = false)
    {
        if ($case) {
            return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
        } else {
            return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
        }
    }

    /**
     * 文字列の等しさ
     * @param string $str1
     * @param string $str2
     * @return bool
     */
    static public function str_equal($str1, $str2, $case = false)
    {
        if ($case) {
            return (strcasecmp($str1, $str2) === 0);
        } else {
            return (strcmp($str1, $str2) === 0);
        }
    }

    /**
     * HMAC-MD5
     * @param string $key
     * @param string $data
     * @return string result
     */
    static public function crypt_hmac_md5($key, $data)
    {
        $b = 64;
        if (strlen($key) > $b) $key = pack("H*", md5($key));
        $key  = str_pad($key, $b, chr(0x00));
        $ipad = str_pad('', $b, chr(0x36));
        $opad = str_pad('', $b, chr(0x5c));
        $k_ipad = $key ^ $ipad;
        $k_opad = $key ^ $opad;
        return md5($k_opad . pack("H*", md5($k_ipad . $data)));
    }

    /**
     * リアルパスの取得
     * @param string $path
     * @return string
     */
    static public function realpath($path)
    {
        $path = realpath($path);
        if (is_dir($path) and !static::endswith($path, DIRECTORY_SEPARATOR)) {
            $path .= DIRECTORY_SEPARATOR;
        }
        return $path;
    }

    /**
     * 日時が正しいか検証
     *
     * @param [type] $date
     * @param string $format
     * @return boolean
     */
    static public function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    /**
     * mb_trim
     * 
     * @see https://qiita.com/fallout/items/a13cebb07015d421fde3
     *
     * @param string $pString
     * @return string
     */
    static public function mb_trim($pString)
    {
        return preg_replace('/\A[\p{C}\p{Z}]++|[\p{C}\p{Z}]++\z/u', '', $pString);
    }

    /**
     * データの保存
     *
     * @param string $path
     * @param mixed $data
     * @param integer $mkdir_mode
     * @return mixed
     */
    static public function file_put_serialize($path, $data)
    {
        $dir = dirname($path);
        if (!file_exists($dir)) {
            mkdir($dir, static::$mode_dir, true);
        }

        // ファイル 新規作成
        $file_chmod = false;
        if (!file_exists($path)) {
            $file_chmod = true;
        }

        foreach (range(1, static::$retry_count) as $i) {
            $file_size = file_put_contents($path,  "<?php exit(); ?>\n" . serialize($data) . "\n", LOCK_EX);
            if (!empty($file_size)) {
                if ($file_chmod) {
                    chmod($path, static::$mode_file);
                }
                return $file_size;
            }
            usleep(static::$retry_usleep);
        }
        return $file_size;
    }

    /**
     * データの取得
     *
     * @param string $path
     * @param array $def
     * @return array
     */
    static public function file_get_serialize($path, $def = array())
    {
        if (!file_exists($path)) {
            return $def;
        }
        $arr = file($path);
        $hash = unserialize($arr[count($arr) - 1]);

        return $hash;
    }

    /**
     * fputcsv
     *
     * @param array $fields
     * @return string
     */
    static public function fputcsv(array $fields)
    {
        $output = array();
        foreach ($fields as $field => $value) {
            $output[] = static::fputcsv_convert($value);
        }

        return implode(',', $output) . "\r\n";
    }

    /**
     * fputcsv convert
     *
     * @param string $value
     * @param boolean $dc
     * @return string
     */
    static public function fputcsv_convert($value, $dc = true)
    {
        if (is_null($value)) {
            return '';
        }
        if ($dc) {
            $value = str_replace('"', '""', $value);
        }

        $value = trim(trim($value), "\'");
        $value = str_replace("\\'", "'", $value);
        #$value = str_replace('{CM}', ',', $value);
        $to = ' ';
        $value = strtr($value, array("\r\n" => $to, "\r" => $to, "\n" => $to));
        $value = '"' . $value . '"';
        return $value;
    }

    /**
     * ファイルポインタから行を取得し、CSVフィールドを処理する
     * @param resource handle
     * @param int length
     * @param string delimiter
     * @param string enclosure
     * @return mixed
     */
    static public function fgetcsv(&$handle, $length = null, $d = ',', $e = '"')
    {
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
     * csv hash
     *
     * @param array $csv_head
     * @param array $csv_col
     * @return mixed $csv_hash
     */
    static public function csv_hash($csv_head, $csv_col)
    {
        if (count($csv_col) == 1 and $csv_col[0] == '') {
            return false;
        }
        $csv_hash = array();
        foreach ($csv_head as $csv_idx => $csv_key) {
            $csv_hash[$csv_key] = isset($csv_col[$csv_idx]) ? $csv_col[$csv_idx] : '';
        }
        return $csv_hash;
    }

    /**
     * csv 保存
     *
     * @param string $csv_path
     * @param array $csv_hash
     * @param integer $size_max
     * @return mixed
     */
    static public function file_put_csv($csv_path, $csv_hash, $size_max = 0)
    {
        // ディレクトリの確認
        $csv_dir = dirname($csv_path);
        if (!file_exists($csv_dir)) {
            mkdir($csv_dir, static::$mode_dir, true);
        }

        $csv_basename = basename($csv_path);
        $csv_filename = substr($csv_basename, 0, strrpos($csv_basename, '.'));
        $csv_extension = substr($csv_basename, strrpos($csv_basename, '.'));

        // csv ローテーション 500KB=512000 1MB=1048576
        if (!empty($size_max)) {
            if (file_exists($csv_path) and filesize($csv_path) > $size_max) {
                $csv_mtime = explode('.', microtime(true));
                rename($csv_path, $csv_dir . DIRECTORY_SEPARATOR . $csv_filename  . '.' . date('Ymd_His') . '_' . $csv_mtime[1] . $csv_extension);
            }
        }

        // ファイル 新規作成
        $file_chmod = false;
        if (!file_exists($csv_path)) {
            $file_chmod = true;
        }

        // csv ヘッダー
        if (!file_exists($csv_path)) {
            foreach (range(1, static::$retry_count) as $i) {
                $file_size = file_put_contents($csv_path, static::fputcsv(array_keys($csv_hash)), FILE_APPEND | LOCK_EX);
                if (!empty($file_size)) {
                    break;
                }
                usleep(static::$retry_usleep);
            }
        }

        // csv 書き込み
        foreach (range(1, static::$retry_count) as $i) {
            $file_size = file_put_contents($csv_path, static::fputcsv(array_values($csv_hash)), FILE_APPEND | LOCK_EX);
            if (!empty($file_size)) {
                if ($file_chmod) {
                    chmod($csv_path, static::$mode_file);
                }
                return $file_size;
            }
            usleep(static::$retry_usleep);
        }
        return $file_size;
    }

    /**
     * log 保存
     *
     * @param string $path
     * @param string $type
     * @param string $message
     * @param integer $size_max
     * @return mixed
     */
    static public function file_put_log($path, $type, $message, $size_max = 1048576)
    {
        $csv_hash = array(
            'date_time' => date('Y-m-d H:i:s w'),
            'type' => $type,
            'message' => $message,
        );
        return static::file_put_csv($path, $csv_hash);
    }
}
