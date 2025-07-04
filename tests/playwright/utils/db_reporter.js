/**
 * A console reporter based on Winston that supports
 * log levels.
 * https://github.com/winstonjs/winston
 *
 * Log levels are defined by Winston library:
 * ```
 * {
 *   error: 0,
 *   warn: 1,
 *   info: 2,
 *   http: 3,
 *   verbose: 4,
 *   debug: 5,
 *   silly: 6
 * }
 * ```
 *
 * Log level can be specified in the reporter options
 * such as `{ level: "debug" }`.
 *
 * info: no debugging statements
 *
 * debug:
 *  - pre-flight test is about to run
 *  - test is about to run
 *
 * silly:
 *  - each step within tests (e.g. locator.fill...)
 *  - console output of the test, such as Drush command output
 */

import {
  createLogger,
  transports as _transports,
  format as _format,
} from 'winston';

class DbReporter {
  constructor(props) {
    this.logger = createLogger({
      level: props?.level || 'info',
      transports: [new _transports.Console()],
      format: _format.combine(_format.splat(), _format.simple()),
    });
    // Monkey-patch console.
    globalThis.console.trace = this.silly.bind(this);
    globalThis.console.debug = this.debug.bind(this);
    globalThis.console.info = this.info.bind(this);
    globalThis.console.warn = this.warn.bind(this);
    globalThis.console.error = this.error.bind(this);
  }

  silly(message, ...meta) {
    this.logger.silly(message, ...meta);
  }

  debug(message, ...meta) {
    this.logger.debug(message, ...meta);
  }

  info(message, ...meta) {
    this.logger.info(message, ...meta);
  }

  warn(message, ...meta) {
    this.logger.warn(message, ...meta);
  }

  error(message, ...meta) {
    this.logger.error(message, ...meta);
  }

  /**
   * This method is called once at the beginning of the test run.
   * @param config {import('@playwright/test/reporter').FullConfig}
   * @param suite {import('@playwright/test/reporter').Suite}
   */
  // eslint-disable-next-line no-unused-vars
  onBegin(config, suite) {
    // Info about target site, in Playwright it's on the project level.
    // In ATK normally it's the same URL for all projects.
    if (config.projects.length) {
      const baseUrl = config.projects[0].use.baseURL;
      this.info(`Running tests against ${baseUrl}`);
    } else {
      this.info('No test project configured.');
    }
  }

  /**
   * This method is called at the start of each test.
   * @param test {import('@playwright/test/reporter').TestCase}
   * @param result {import('@playwright/test/reporter').TestResult}
   */
  // eslint-disable-next-line no-unused-vars
  onTestBegin(test, result) {
    this.debug('Test about to run: %s', test.title);
  }

  onError(e) {
    this.error(e.stack);
  }

  /**
   * This method is called for each step within a test.
   * @param test {import('@playwright/test/reporter').TestCase}
   * @param status {import('@playwright/test/reporter').TestResult}
   * @param step {import('@playwright/test/reporter').TestStep}
   */
  onStepBegin(test, status, step) {
    this.silly(step.title);
  }

  // eslint-disable-next-line class-methods-use-this
  onStepEnd() {}

  /**
   * This method is called when a test finished. By this time,
   * the test result object is fully populated with the test status
   * and errors.
   * @param test {import('@playwright/test/reporter').TestCase}
   * @param result {import('@playwright/test/reporter').TestResult}
   */
  onTestEnd(test, result) {
    this.debug(
      'Test %s: %s%s',
      result.status,
      test.title,
      result.status === 'failed' ? `\n${result.error.stack}` : '',
    );
  }

  /**
   * This method is called once all test have finished running.
   * @param result {import('@playwright/test/reporter').FullResult}
   */
  // eslint-disable-next-line class-methods-use-this,no-unused-vars
  onEnd(result) {}

  onStdOut(data) {
    // Trace test output into console.
    this.silly(data);
  }

  onStdErr(data) {
    // Trace test output into console.
    this.silly(data);
  }
}

export default DbReporter;
