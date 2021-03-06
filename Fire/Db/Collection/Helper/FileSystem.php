<?php

namespace Fire\Db\Collection\Helper;

use DateTime;
use Fire\Db\Collection\Config;

/**
 * This is a helper class to model the filesystem and handle reads/writes for the collection.
 */

class FileSystem
{

    /**
     * Constants
     */
    const FILE_META = '/.meta';
    const DIRECTORY_INDEX = '/index';
    const DIRECTORY_DOCUMENT = '/document';
    const DIRECTORY_DOCUMENT_META = '/document/meta';

    /**
     * The location of the direcotry in the filesystem that represents the colletion.
     * @var String
     */
    private $_dir;

    /**
     * The meta file of the collection.
     * @var String
     */
    private $_metaFile;

    /**
     * The location of the directory where index data will be stored.
     * @var String
     */
    private $_indexDir;

    /**
     * The location of the directory where documents will be stored.
     * @var String
     */
    private $_docsDir;

    /**
     * The location of the directory where document meta data will be stored.
     * @var String
     */
    private $_metaDir;

    /**
     * Meta data loaded from the self::$_metaFile location.
     * @var Object|FALSE
     */
    private $_metaData;

    /**
     * The Constructor
     * @param String $directory The location of the directory where the collection exists
     */
    public function __construct($directory)
    {
        $this->_dir = $directory;
        $this->_metaFile = $this->_dir . self::FILE_META;
        $this->_indexDir = $this->_dir . self::DIRECTORY_INDEX;
        $this->_docsDir = $this->_dir . self::DIRECTORY_DOCUMENT;
        $this->_metaDir = $this->_dir . self::DIRECTORY_DOCUMENT_META;
        $this->_metaData = false;

        //if the filesystem isn't setup properly, set it up
        if (!is_dir($this->_indexDir)) {
            mkdir($this->_indexDir);
        }
        if (!is_dir($this->_docsDir)) {
            mkdir($this->_docsDir);
        }
        if (!is_dir($this->_metaDir)) {
            mkdir($this->_metaDir);
        }
        //if the collection meta data file is missing, create it
        if (!$this->collectionMetaDataExists()) {
            $metaData = (object) [
                'name' => basename($this->_dir),
                'config' => new Config(),
                'registry' => md5($this->generateUniqueId())
            ];
            $this->writeCollectionMetaData($metaData);
        }
    }

    /**
     * Determines if collection meta data exists.
     * @return Boolean
     */
    public function collectionMetaDataExists()
    {
        return file_exists($this->_metaFile);
    }

    /**
     * Returns meta data from the filesystem.
     * @return Object
     */
    public function getCollectionMetaData()
    {
        if (!$this->_metaData) {
            $this->_metaData = $this->_getPhpObjectFile($this->_metaFile);
        }
        return $this->_metaData;
    }

    /**
     * Writes collection meta data to the filesystem.
     * @param Object $metaData
     */
    public function writeCollectionMetaData($metaData)
    {
        $this->_metaData = false;
        $this->_writePhpObjectFile($this->_metaFile, $metaData);
    }

    /**
     * Determines if a document exists in the filesystem.
     * @param String $documentId
     * @return Boolean
     */
    public function documentExists($documentId)
    {
        return file_exists($this->_metaDir . '/' . $documentId);
    }

    /**
     * Returns a document's meta data.
     * @param  String $documentId
     * @return String
     */
    public function getDocumentMetaData($documentId)
    {
        $documentMetaFilePath = $this->_metaDir . '/' . $documentId;
        return $this->_getFile($documentMetaFilePath);
    }

    /**
     * Writes a document's meta data.
     * @param String $documentId
     * @param String $documentMetaFile
     */
    public function writeDocumentMetaData($documentId, $documentMetaFile)
    {
        $documentMetaFilePath = $this->_metaDir . '/' . $documentId;
        $this->_writeFile($documentMetaFilePath, $documentMetaFile);
    }

    /**
     * Deletes a document's meta data from the file system.
     * @param String $documentId
     * @return Void
     */
    public function deleteDocumentMetaData($documentId)
    {
        if($this->documentExists($documentId)) {
            unlink($this->_metaDir . '/' . $documentId);
        }
    }

    /**
     * Returns a document from the filesystem.
     * @param String $documentId
     * @param String $revision
     * @return Object
     */
    public function getDocument($documentId, $revision = null)
    {
        if ($this->documentExists($documentId)) {
            if (is_null($revision)) {
                $revision = $this->getDocumentMetaData($documentId);
            }
            $documentFile = $this->_docsDir . '/' . $documentId . '.' . $revision;
            if (file_exists($documentFile)) {
                return $this->_getJsonFile($documentFile);
            }
        }
        return null;
    }

    /**
     * Writes a document to the fileystem.
     * @param String $documendId
     * @param Object $document
     * @return Object
     */
    public function writeDocument($documendId, $document)
    {
        $documentId = (!is_null($documendId)) ? $documendId : $this->generateUniqueId();
        $revision = $this->_generateRevisionNumber();
        $created = $this->_generateTimestamp();
        $documentFile = $this->_docsDir . '/' . $documentId . '.' . $revision;
        $document->__id = $documentId;
        $document->__revision = $revision;
        $document->__timestamp = $created;
        $this->_writeJsonFile($documentFile, $document);

        return $document;
    }

    /**
     * Determines if an index exists in the filesystem.
     * @param String $indexId
     * @return Boolean
     */
    public function indexExists($indexId)
    {
        $indexFile = $this->_indexDir . '/' . $indexId;
        return file_exists($indexFile);
    }

    /**
     * Returns an index file from the filesystem.
     * @param  String $indexId
     * @return String
     */
    public function getIndex($indexId)
    {
        if ($this->indexExists($indexId)) {
            $indexFile = $this->_indexDir . '/' . $indexId;
            return $this->_getFile($indexFile);
        }

        return '';
    }

    /**
     * Writes an index file to the filesystem.
     * @param String $indexId
     * @param String $index
     */
    public function writeIndex($indexId, $index)
    {
        $indexFile = $this->_indexDir . '/' . $indexId;
        $this->_writeFile($indexFile, $index);
    }

    /**
     * Sets the configuration for the collection.
     * @param Fire\Db\Collection\Config $config
     */
    public function setConfiguration(Config $config)
    {
        $metaData = $this->getCollectionMetaData();
        $metaData->config = $config;
        $this->writeCollectionMetaData($metaData);
    }

    /**
     * Gets the configuration for the collection.
     * @return Fire\Db\Collection\Config
     */
    public function getConfiguration()
    {
        $metaData = $this->getCollectionMetaData();
        return $metaData->config;
    }

    /**
     * Generates a unique id.
     * @return String
     */
    public function generateUniqueId()
    {
        $rand = uniqid(rand(10, 99));
        $time = microtime(true);
        $micro = sprintf('%06d', ($time - floor($time)) * 1000000);
        $date = new DateTime(date('Y-m-d H:i:s.' . $micro, $time));
        $id = $date->format('YmdHisu') . $rand;
        return $id;
    }

    /**
     * Returns a php object from the filesystem.
     * @param String $phpObjectFilePath
     * @return Object
     */
    private function _getPhpObjectFile($phpObjectFilePath)
    {
        return unserialize($this->_getFile($phpObjectFilePath));
    }

    /**
     * Writes a php object to the filesystem.
     * @param String $phpObjectFilePath
     * @param Object $phpObj
     */
    private function _writePhpObjectFile($phpObjectFilePath, $phpObj)
    {
        $this->_writeFile($phpObjectFilePath, serialize($phpObj));
    }

    /**
     * Returns a JSON file from the filesytem.
     * @param String $jsonFilePath
     * @return Object
     */
    private function _getJsonFile($jsonFilePath)
    {
        return json_decode($this->_getFile($jsonFilePath));
    }

   /**
    * Writes a JSON file to the filesystem.
    * @param String $jsonFilePath
    * @param Object $jsonObj
    */
    private function _writeJsonFile($jsonFilePath, $jsonObj)
    {
        $this->_writeFile($jsonFilePath, json_encode($jsonObj));
    }

   /**
    * Returns a file from the filesystem.
    * @param String $filePath
    * @return String
    */
    private function _getFile($filePath)
    {
        return file_get_contents($filePath);
    }

   /**
    * Writes a file to the filesystem.
    * @param String $filePath
    * @param String $file
    */
    private function _writeFile($filePath, $file)
    {
        $success = file_put_contents($filePath, $file);
        if ($success === false) {
            $this->_writeFile($filePath, $file);
        }
    }

   /**
    * Generates a revision number.
    * @return Integer
    */
    private function _generateRevisionNumber()
    {
        return rand(1000001, 9999999);
    }

   /**
    * Generates a timestamp.
    * @return String
    */
    private function _generateTimestamp()
    {
        $time = microtime(true);
        $micro = sprintf('%06d', ($time - floor($time)) * 1000000);
        $date = new DateTime(date('Y-m-d H:i:s.' . $micro, $time));
        return $date->format("Y-m-d H:i:s.u");
    }

}
