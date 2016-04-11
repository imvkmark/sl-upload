<?php namespace Imvkmark\SlUpload\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Imvkmark\SlUpload\Action\SlUploadImage;


/**
 * 服务接口
 * Class UploadController
 * @package App\Http\Controllers\Support
 */
class SlUploadController extends Controller {

	protected $action;

	/**
	 * 图片上传组件的后端
	 * @param Request $request
	 *    'kindeditor' => 'imgFile',
	 *    'avatar'     => '__avatar1',
	 *    'thumb'      => 'Filedata',
	 *    'default'    => 'image_file',
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	public function postImage(Request $request) {
		$field = $request->input('field', 'image_file');
		$sign  = $request->input('upload_token', '');

		// 匹配
		$file  = \Input::file($field);
		$Image = new SlUploadImage();
		if ($Image->checkUpload($sign) && $Image->save($file)) {
			return site_end('success', '图片上传成功', [
				'json'        => true,
				'success'     => 1,                    // editormd
				'message'     => '上传成功',            // editormd
				'url'         => $Image->getUrl(),     // editormd
				'destination' => $Image->getDestination(),
			]);
		} else {
			return site_end('error', $Image->getError(), [
				'json'    => true,
				'success' => 0,                   // editormd
				'message' => $Image->getError(),  // editormd
			]);
		}
		// kindeditor
		// {"error" : 0,"url" : "' . $url . '"}
		// avatar
		// update avatar
		// thumb
		// url, path
	}

}
