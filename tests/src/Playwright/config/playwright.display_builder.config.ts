/*
 * Display Builder tests configuration.
 */
export default {
  keyboardTimeout: 350, // Because keyboard.js has a 300 ms highlight of the clicked button.

  dbList: 'admin/structure/display-builder/instances',

  viewsDbList: 'admin/structure/views/display-builder',

  pageListUrl: 'admin/structure/page-layout',
  pageAddUrl: 'admin/structure/page-layout/add',

  // PageLayout::getPrefix()
  pagePrefix: 'page_layout__',
  // EntityViewDisplay::getPrefix()
  entityPrefix: 'entity_view__',
  // DisplayExtender::getPrefix()
  viewsPrefix: 'views__',
}
