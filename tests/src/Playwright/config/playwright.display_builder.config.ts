/*
 * Display Builder tests configuration.
 */
export default {
  keyboardTimeout: 400, // Because keyboard.js has a 300 ms highlight of the clicked button.

  dbList: 'admin/structure/display-builder/instances',

  viewsDbList: 'admin/structure/views/display-builder',

  pageListUrl: 'admin/structure/page-layout',
  pageAddUrl: 'admin/structure/page-layout/add',

  devAddInstance: 'admin/structure/display-builder/instance/add',
  devViewInstance: 'admin/structure/display-builder/instance/{instance_id}',

  // PageLayout::getPrefix()
  pagePrefix: 'page_layout__',
  // EntityViewDisplay::getPrefix()
  entityPrefix: 'entity_view__',
  // DisplayExtender::getPrefix()
  viewsPrefix: 'views__',
  // MockEntity::getPrefix()
  develPrefix: 'devel__',

  keyFullscreen: 'Shift+F',
  keyHighlight: 'Shift+H',
}
