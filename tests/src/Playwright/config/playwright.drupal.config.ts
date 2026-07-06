/*
 * Tests configuration.
 */
export default {
  operatingMode: 'native',
  drushCmd: 'drush',

  contentTypesList: 'admin/structure/types',
  contentTypesAdd: 'admin/structure/types/add',
  contentTypesEdit: 'admin/structure/types/manage/{content_type}',
  contentTypesFields: 'admin/structure/types/manage/{content_type}/fields',
  contentTypesFormDisplay: 'admin/structure/types/manage/{content_type}/form-display',
  contentTypesDisplay: 'admin/structure/types/manage/{content_type}/display/{display}',
  contentTypesPermissions: 'admin/structure/types/manage/{content_type}/permissions',

  contentList: 'admin/content',
  contentAdd: 'node/add',
  contentTypeAdd: 'node/add/{bundle}',
  contentEdit: 'node/{nid}/edit',
  contentDelete: 'node/{nid}/delete',

  viewsList: 'admin/structure/views',
  viewsAddUrl: 'admin/structure/views/add',
  viewsEditUrl: 'admin/structure/views/view/{view_id}/edit',

  logInUrl: 'user/login',
  logOutUrl: 'user/logout',

  modules: 'admin/modules',
  performance: 'admin/config/development/performance',
  statusReport: 'admin/reports/status',

  pantheon: {
    isTarget: false,
    site: 'aSite',
    environment: 'dev',
  },

  targetSite: {
    isTarget: false,
    root: null, // optional
    remoteHost: 'localhost',
    remoteUser: null, // optional
    sshOptions: '-p 2222', // optional
  },
}
