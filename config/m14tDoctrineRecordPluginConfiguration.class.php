<?php
class m14tDoctrineRecordPluginConfiguration extends sfPluginConfiguration {

  public function initialize() {
    $this->dispatcher->connect(
      'doctrine.filter_model_builder_options',
      array('m14tDoctrineRecordPluginConfiguration', 'modifyBuildParams')
    );
  }


  static public function modifyBuildParams(sfEvent $event, $options) {
    if ( 'sfDoctrineRecord' === $options['baseClassName'] ) {
      //-- Only override it if it hasn't been overridden already.
      $options['baseClassName'] = 'm14tDoctrineRecord';
    }
    return $options;
  }


}
