&lt;?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Installation script for
 *
 * @author No-CMS Module Generator
 */
class {{ main_controller }} extends CMS_Controller {
    public function index(){
    	$data['content'] = $this->cms_submenu_screen('{{ navigation_parent_name }}');
        $this->view('{{ directory }}/{{ main_controller }}_index', $data, '{{ navigation_parent_name }}');
    }
}