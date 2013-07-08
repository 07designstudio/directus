<?php

namespace Directus\Media\Storage;

use Directus\Bootstrap;
use Directus\Db\TableGateway\DirectusSettingsTableGateway;
use Directus\Db\TableGateway\DirectusStorageAdaptersTableGateway;
use Directus\Media\Thumbnail;
use Directus\Util\Formatting;

class Storage {

	const ADAPTER_NAMESPACE = "\\Directus\\Media\\Storage\\Adapter";

    /** @var DirectusSettingsTableGateway */
    protected $settings;

    /** @var array */
    protected $mediaSettings = array();

    /** @var array */
    protected static $storages = array();

    public function __construct() {
        $this->acl = Bootstrap::get('acl');
        $this->adapter = Bootstrap::get('ZendDb');
        // Fetch media settings
        $Settings = new DirectusSettingsTableGateway($this->acl, $this->adapter);
        $this->mediaSettings = $Settings->fetchCollection('media', array(
            'storage_adapter','storage_destination','thumbnail_storage_adapter',
            'thumbnail_storage_destination', 'thumbnail_size', 'thumbnail_quality'
        ));
        // Initialize Storage Adapters
        $StorageAdapters = new DirectusStorageAdaptersTableGateway($this->acl, $this->adapter);
        $adapterRoles = array('DEFAULT','THUMBNAIL');
        $storage = $StorageAdapters->fetchByUniqueRoles($adapterRoles);
        if(count($storage) !== count($adapterRoles)) {
            throw new \RuntimeException(__CLASS__ . ' expects adapter settings for these default adapter roles: ' . implode(',', $adapterRoles));
        }
        $this->MediaStorage = self::getStorage($storage['DEFAULT']['adapter_name'], $storage['DEFAULT']['params']);
        $this->ThumbnailStorage = self::getStorage($storage['THUMBNAIL']['adapter_name'], $storage['THUMBNAIL']['params']);
        $this->storageAdaptersByRole = $storage;
    }

    /**
     * @param  string $adapterName
     * @param  array $adapterParams
     * @return \Directus\Media\Storage\Adapter\Adapter
     */
    public static function getStorage($adapterName, array $adapterParams) {
        $cacheKey = $adapterName . serialize($adapterParams);
        if(!isset(self::$storages[$cacheKey])) {
			$adapterClass = self::ADAPTER_NAMESPACE . "\\$adapterName";
			if(!class_exists($adapterClass)) {
				throw new \RuntimeException("No such adapter class: $adapterClass");
			}
            self::$storages[$cacheKey] = new $adapterClass($adapterParams);
        }
        return self::$storages[$cacheKey];
    }

    public function acceptFile($localFile, $targetFileName) {
        $settings = $this->mediaSettings;
        $fileData = $this->MediaStorage->getUploadInfo($localFile);

        // Generate thumbnail if image
        $thumbnailTempName = null;
        $info = pathinfo($targetFileName);
        if(in_array($info['extension'], array('jpg','jpeg','png','gif'))) {
            $img = Thumbnail::generateThumbnail($localFile, $info['extension'], $settings['thumbnail_size']);
            $thumbnailTempName = tempnam(sys_get_temp_dir(), 'DirectusThumbnail');
            Thumbnail::writeImage($info['extension'], $thumbnailTempName, $img, $settings['thumbnail_quality']);
        }

        // Push original file
        $mediaDestination = $this->storageAdaptersByRole['DEFAULT']['destination'];
        $finalPath = $this->MediaStorage->acceptFile($localFile, $targetFileName, $mediaDestination);
        $fileData['name'] = basename($finalPath);
        $fileData['title'] = Formatting::fileNameToFileTitle($fileData['name']);
        $fileData['date_uploaded'] = gmdate('Y-m-d H:i:s');

        // Push thumbnail file if applicable (if image)
        if(!is_null($thumbnailTempName)) {
            $thumbnailDestination = $this->storageAdaptersByRole['THUMBNAIL']['destination'];
            $this->ThumbnailStorage->acceptFile($thumbnailTempName, $fileData['name'], $thumbnailDestination);
        }

        return $fileData;
    }

}
