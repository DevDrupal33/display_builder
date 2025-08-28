/*
 * Tests configuration.
 */
export default {
  operatingMode: 'native',
  drushCmd: 'drush',

  viewsList: 'admin/structure/views',
  viewsAddUrl: 'admin/structure/views/add',
  viewsEditUrl: 'admin/structure/views/view/{view_id}/edit',

  logInUrl: 'user/login',
  logOutUrl: 'user/logout',

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
