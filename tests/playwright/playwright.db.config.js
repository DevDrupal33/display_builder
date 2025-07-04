/*
* Display Builder tests configuration.
*/
module.exports = {
  operatingMode: 'native',
  drushCmd: 'drush',

  dbList: 'admin/structure/display-builder/index',
  dbAddUrl: 'admin/structure/display-builder/instance/add',
  dbDeleteUrl: 'admin/structure/display-builder/instance/{db_id}/delete',
  dbDeleteAllUrl: 'admin/structure/display-builder/instance/delete-all',
  dbEditUrl: 'admin/structure/display-builder/instance/{db_id}/edit',

  logInUrl: 'user/login',
  logOutUrl: 'user/logout',
  registerUrl: 'user/register',
  resetPasswordUrl: 'user/password',

  authDir: 'tests/e2e/support',
  dataDir: 'tests/data',
  supportDir: 'tests/utils',
  testDir: 'e2e',

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
