<?php
/**
 * @author WebAsyst Team
 *
 */
class blogBlogSettingsAction extends waViewAction
{

    public function execute()
    {

        $this->setLayout(new blogDefaultLayout());

        $blog_id = (int) waRequest::get('blog');
        $blog_model = new blogBlogModel();

        if (($blog_id && ($this->getRights("blog.{$blog_id}")<blogRightConfig::RIGHT_FULL)) || (!$blog_id && !$this->getRights(blogRightConfig::RIGHT_ADD_BLOG))) {
            throw new waRightsException(_w('Access denied'));
        }

        // save settings (POST)
        $settings = waRequest::post('settings');

        $draft_data = array();

        if ($settings) {

            $settings['status'] = (isset($settings['status'])) ? blogBlogModel::STATUS_PUBLIC : blogBlogModel::STATUS_PRIVATE;
            $settings['name'] = trim($settings['name']);
            $settings['icon'] = (!empty($settings['icon_url'])) ? $settings['icon_url'] : $settings['icon'];

            if (isset($settings['qty'])) {
                unset($settings['qty']);
            }

            if (isset($settings['sort'])) {
                unset($settings['sort']);
            }

			$settings['id'] = $blog_id;
            $validate_massages = $this->validate($settings);

            if (!$validate_massages) {

                if ($blog_id) {
                    $blog_model->updateById($blog_id, $settings);
                } else {
                    $settings['sort'] = (int)$blog_model->select('MAX(`sort`)')->fetchField() + 1;
                    $blog_id = $blog_model->insert($settings);
                    $this->getUser()->setRight($this->getApp(), "blog.{$blog_id}", blogRightConfig::RIGHT_FULL);
                }

                // refresh qty post in blogs
                $blog_model->recalculate($blog_id);
                $this->redirect(array(
                	'blog' => $blog_id
                ));
            } else {
                $this->view->assign('messages', $validate_massages);
                $draft_data = $settings;
            }
        }

        $colors = $this->getConfig()->getColors();
        $icons = $this->getConfig()->getIcons();

        if ($blog_id) {

            if (!$blog = $blog_model->search(array(
					'blog' => $blog_id
				), array(
					'link' => false
				))->fetchSearchItem()
			) {
            	throw new waException(_w('Blog not found'), 404);
            }

            $blog['other_settlements'] = blogBlogModel::getPureSettlements($blog);
            $blog['settlement'] = array_shift($blog['other_settlements']);

        } else {

            $blog = array(
            	'id' => false,
				'name' => '',
				'status' => blogBlogModel::STATUS_PUBLIC,
				'icon' => current($icons),
				'color' => current($colors),
				'url'=> false,

            );

            $blog = array_shift($blog_model->prepareView(array(
            	$blog
            ), array(
            	'link' => false
            )));

            $blog['other_settlements'] = blogBlogModel::getPureSettlements($blog);
            $blog['settlement'] = array_shift($blog['other_settlements']);

        }

        $this->getResponse()->setTitle($blog_id ? trim(sprintf(_w('%s settings'), $blog['name'])) : _w('New blog'));

        $blog = !$draft_data ? $blog : array_merge($blog, $draft_data);
        $posts_total_count = 0;
        if ($blog_id) {
            $post_model = new blogPostModel();
            $posts_total_count = $post_model->countByField('blog_id',$blog_id);
            if ($posts_total_count) {
                $blog_model = new blogBlogModel();
                $blogs = $blog_model->getAvailable($this->getUser());
                $this->view->assign('blogs', $blogs);
            }
        }
        $this->view->assign('posts_total_count',$posts_total_count);

        $this->view->assign('blog_id', $blog_id);
        $this->view->assign('blog', $blog);
        $this->view->assign('colors', $colors);
        $this->view->assign('icons', $icons);
    }

    public function validate(&$data)
    {
        $messages = array();

        if ($data['status'] != blogBlogModel::STATUS_PRIVATE) {
	        if (isset($data['id'])) {
	            $url_validator = new blogSlugValidator(array('id' => $data['id']));
	        } else {
	            $url_validator = new blogSlugValidator();
	        }

	        $url_validator->setSubject(blogSlugValidator::SUBJECT_BLOG);

	        $name_validator = new waStringValidator(
	        	array(
					'max_length' => 255,
					'required' => true
	        	), array(
					'required' => _w('Blog name must not be empty')
	        	)
	        );

	        if (!$url_validator->isValid($data['url'])) {
	            $messages['blog_url'] = current($url_validator->getErrors());
	        }
	        if (!$name_validator->isValid($data['name'])) {
	            $messages['blog_name'] = current($name_validator->getErrors());
	        }
        } else {
        	$blog_model = new blogBlogModel();
        	if (!$data['id']) {
        		$data['url'] = $blog_model->genUniqueUrl($data['name']);
        	} else {
        		$url = $blog_model->select('url')->where('id = i:id', array('id' => $data['id']))->fetchField('url');
        		$data['url'] = $url ? $url : $blog_model->genUniqueUrl($data['name']);
        	}
        }

        return $messages;

    }
}