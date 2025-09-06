/*
 * Display Builder tests configuration.
 */
export default {
  keyboardTimeout: 350, // Because keyboard.js has a 300 ms highlight of the clicked button.

  dbList: 'admin/structure/display-builder/instances',

  viewsDbList: 'admin/structure/views/display-builder',

  pageListUrl: 'admin/structure/page-layout',
  pageAddUrl: 'admin/structure/page-layout/add',

  devAddInstance: 'admin/structure/display-builder/instance/add',

  // PageLayout::getPrefix()
  pagePrefix: 'page_layout__',
  // EntityViewDisplay::getPrefix()
  entityPrefix: 'entity_view__',
  // DisplayExtender::getPrefix()
  viewsPrefix: 'views__',
  // MockEntity::getPrefix()
  develPrefix: 'devel__',

  keyFullscreen: 'Shift+M',
  keyHighlight: 'Shift+H',
}
