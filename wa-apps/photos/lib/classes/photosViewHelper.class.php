<?php

class photosViewHelper extends waAppViewHelper
{
    /**
     *
     * Get data array from photos collection
     * @param string $hash selector hash
     * @param int|string|null $size numerical size or size name
     * @param int $offset optional parameter
     * @param int $limit optional parameter
     *
     * If $limit is omitted but $offset is not than $offset is interpreted as 'limit' and method returns first 'limit' items
     * If $limit and $offset are omitted that method returns first 500 items
     *
     * @return array
     */
    public function photos($hash = '', $size = null, $offset = null, $limit = null)
    {
        $size = !is_null($size) ? $size : photosPhoto::getThumbPhotoSize();
        $sizes = array();
        foreach (explode(',', $size) as $s) {
            $sizes[] = 'thumb_'.trim($s);
        }
        $sizes = implode(',', $sizes);
        $collection = new photosCollection($hash);
        if (!$limit && $offset) {
            $limit = $offset;
            $offset = 0;
        }
        if (!$offset && !$limit) {
            $offset = 0;
            $limit = 500;
        }
        return $collection->getPhotos("*,frontend_link,tags,".$sizes, $offset, $limit, true);
    }

    /**
     *
     * Get photo data by id
     * @param int $id
     * @param int|string $size numerical size or size name
     * @return array
     */
    public function photo($id, $size = null)
    {
        $id = max(1,intval($id));
        return array_shift($this->photos("id/{$id}", $size));
    }

    public function option($name)
    {
        return wa('photos')->getConfig()->getOption($name);
    }

    /**
     *
     * Get photos albums tree
     * @return string
     */
    public function albums($return_html = true)
    {
        $album_model = new photosAlbumModel();
        $albums = $album_model->getAlbums(true);
        if ($return_html) {
            $tree = new photosViewTree($albums);
            return $tree->display('frontend');
        } else {
            foreach ($albums as $album_id => $album) {
                $albums[$album_id]['url'] = photosFrontendAlbum::getLink($album);
                if ($album['parent_id'] && isset($albums[$album['parent_id']])) {
                    $albums[$album['parent_id']]['childs'][] = &$albums[$album_id];
                }
            }
            foreach ($albums as $album_id => $album) {
                if ($album['parent_id']) {
                    unset($albums[$album_id]);
                }
            }
            return $albums;
        }
    }

    /**
     *
     * Get photos tags list
     * @return array
     */
    public function tags()
    {
        $photo_tag_model = new photosTagModel();
        $cloud = $photo_tag_model->getCloud();
        foreach ($cloud as &$tag) {
            $tag['name'] = photosPhoto::escape($tag['name']);
        }
        unset($tag);
        return $cloud;
    }

    /**
     * Get image with special predefined attributes needed for RIA UI in frontend
     *
     * @param array $photo
     * @param string $size
     * @param array $attributes user-attribure, e.g. class or style
     */
    public function getImgHtml($photo, $size, $attributes = array())
    {
        $attributes['data-size'] = $size;
        $attributes['data-photo-id'] = $photo['id'];
        $attributes['class'] = !empty($attributes['class']) ? $attributes['class'] : '';
        $attributes['class'] .= ' photo_img';    // !Important: obligatory class. Need in frontend JS
        return photosPhoto::getEmbedImgHtml($photo, $size, $attributes);
    }
    
    public function ratingHtml($rating, $size = 10, $show_when_zero = false)
    {
        return photosPhoto::getRatingHtml($rating, $size, $show_when_zero);
    }
}
