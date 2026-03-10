/*
 * Display Builder tests configuration.
 */
export default {
  dbList: 'admin/structure/display-builder/instances',
  dbViewUrl: '/admin/structure/display-builder/instance/{instance_id}',

  viewsDbList: 'admin/structure/views/display-builder',

  pageListUrl: 'admin/structure/page-layout',
  pageAddUrl: 'admin/structure/page-layout/add',
  pageViewUrl: 'admin/structure/page-layout/{instance_id}/builder',

  devAddInstance: 'admin/structure/display-builder/instance/add',

  // PageLayout::getPrefix()
  pagePrefix: 'page_layout__',
  // EntityViewDisplay::getPrefix()
  entityPrefix: 'entity_view__',
  // DisplayExtender::getPrefix()
  viewsPrefix: 'views__',
  // StandaloneEntity::getPrefix()
  develPrefix: 'standalone__',

  keyFullscreen: 'Shift+F',
  keyHighlight: 'Shift+H',

  startDrawerID: '#db-first-drawer',
  endDrawerID: '#db-second-drawer',
}
