<?php namespace Imvkmark\SlUpload\Action;

use App\Lemon\Action\ActionBasic;
use App\Lemon\Helper\LmEnv;
use App\Lemon\Helper\LmImage;
use App\Lemon\Project\SysCrypt;
use Imvkmark\SlUpload\Helper\SlUpload;
use Imvkmark\SlUpload\Models\SlImageKey;
use Imvkmark\SlUpload\Models\SlImageUpload;
use Intervention\Image\Constraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SlUploadImage extends ActionBasic {

	protected $isCheckSign = false;
	protected $accountId   = 0;
	protected $destination = '';

	/**
	 * 保存文件, 保存到某开发者下面
	 * @param UploadedFile $file
	 * @return mixed
	 */
	public function save(UploadedFile $file) {
		if (!$this->isCheckSign) {
			return $this->setError('尚未验证上传验签码');
		}
		if ($file->isValid()) {
			// 存储
			$allowedExtensions = [
				'png',
				'jpg',
				'gif',
				'jpeg',   // android default
			];
			if ($file->getClientOriginalExtension() && !in_array($file->getClientOriginalExtension(), $allowedExtensions)) {
				return $this->setError('你只允许上传 "' . implode(',', $allowedExtensions) . '" 格式');
			}

			// 图片存储的磁盘
			$diskName = SlUpload::disk();
			// 磁盘对象
			$Disk = \Storage::disk($diskName);

			$imageExtension       = $file->getClientOriginalExtension() ?: 'png';
			$imageName            = date('is') . str_random(8) . '.' . $imageExtension;
			$imageRelativePath    = date("Ym", time()) . '/' . date("d") . '/' . date("H") . '/' . $imageName;
			$imageSaveDestination = (SlUpload::dir() ? SlUpload::dir() . '/' : '') . $imageRelativePath;
			$imageContent         = file_get_contents($file);

			$Disk->put($imageSaveDestination, $imageContent);
			$imageRealPath = disk_path($diskName) . $imageSaveDestination;

			// 缩放图片
			if ($file->getClientOriginalExtension() != 'gif') {
				$Image = \Image::make($imageRealPath);
				$Image->resize(1440, null, function (Constraint $constraint) {
					$constraint->aspectRatio();
					$constraint->upsize();
				});
				$Image->save();
			}

			// 保存图片
			$imageInfo = LmImage::getImageInfo($imageRealPath);
			SlImageUpload::create([
				'upload_path'      => $imageSaveDestination,
				'upload_type'      => 'image',
				'upload_extension' => $file->getClientOriginalExtension(),
				'upload_filesize'  => $imageInfo['size'],
				'upload_mime'      => $imageInfo['mime'],
				'image_type'       => $imageInfo['type'],
				'image_width'      => $imageInfo['width'],
				'image_height'     => $imageInfo['height'],
				'account_id'       => $this->accountId,
			]);
			$this->destination = $imageRelativePath;
			return true;
		} else {
			return $this->setError($file->getErrorMessage());
		}
	}

	public function getDestination() {
		return $this->destination;
	}

	/**
	 * 图片url的地址
	 * @param $size
	 * @return string
	 */
	public function getUrl() {
		return SlUpload::url($this->destination);
	}

	/**
	 * 验证签名
	 * @param $public
	 * @param $sign
	 * @return bool
	 */
	public function checkUpload($sign) {

		// 令牌是否存在
		$validator = \Validator::make([
			'sign' => $sign,
		], [
			'sign' => 'required',
		], [
			'sign.required' => '上传令牌不存在',
		]);
		if ($validator->fails()) {
			return $this->setError($validator->errors());
		}

		// 反解令牌
		try {
			$deCode = SysCrypt::decode($sign);
		} catch (\Exception $e) {
			return $this->setError('令牌解析失败!');
		}
		$info = explode('|', $deCode);

		// 是否是上传令牌
		$isUpload = ($info[0] == 'upload');
		if (!$isUpload) {
			return $this->setError('令牌类型不正确, 应该生成上传令牌!');
		}

		// 令牌失效时间
		$unix_time = $info[3];
		$expires   = config('sl-upload.expires') ?: 3600;
		$diff      = abs(LmEnv::time() - $unix_time);
		if ($expires * 60 < $diff) {
			return $this->setError('上传令牌已过期, 有效期为 `' . config('sl-upload.time_diff') . '` 分钟');
		}

		// 令牌是否正确, kv 是否相符
		$public = $info[1];
		$secret = $info[2];
		if ($public && $secret) {
			$serverSecret = SlImageKey::getSecretByPublic($public);
			if ($secret != $serverSecret) {
				return $this->setError('令牌不匹配, 冒牌令牌');
			}
		} else {
			return $this->setError('服务器尚未设置访问的密钥');
		}


		$this->accountId = SlImageKey::getAccountIdByPublic($public);
		\Log::debug($info);
		\Log::debug($this->accountId);
		$this->isCheckSign = true;
		return true;
	}
}