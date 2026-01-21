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

import { createLogger, transports as _transports, format as _format } from 'winston'
import type { FullConfig, Suite, TestCase, TestResult, TestStep, FullResult } from '@playwright/test/reporter'

interface ReporterProps {
  level?: string
}

class Reporter {
  private logger: ReturnType<typeof createLogger>

  constructor (props: ReporterProps) {
    this.logger = createLogger({
      level: props?.level || 'info',
      transports: [ new _transports.Console() ],
      format: _format.combine(_format.splat(), _format.simple()),
    })
    // Monkey-patch console.
    globalThis.console.trace = this.silly.bind(this)
    globalThis.console.debug = this.debug.bind(this)
    globalThis.console.info = this.info.bind(this)
    globalThis.console.warn = this.warn.bind(this)
    globalThis.console.error = this.error.bind(this)
  }

  silly (message: any, ...meta: any[]) {
    this.logger.silly(message, ...meta)
  }

  debug (message: any, ...meta: any[]) {
    this.logger.debug(message, ...meta)
  }

  info (message: any, ...meta: any[]) {
    this.logger.info(message, ...meta)
  }

  warn (message: any, ...meta: any[]) {
    this.logger.warn(message, ...meta)
  }

  error (message: any, ...meta: any[]) {
    this.logger.error(message, ...meta)
  }

  onBegin (config: FullConfig, suite: Suite) {
    if (config.projects.length) {
      const baseUrl = config.projects[0].use?.baseURL
      const logLevel = process.env.PLAYWRIGHT_DEBUG_LEVEL || 'debug'
      this.info(`Running tests against: ${baseUrl}, log level: ${logLevel}`)
    } else {
      this.info('No test project configured.')
    }
  }

  onTestBegin (test: TestCase, result: TestResult) {
    this.debug(`Start test: ${test.title}`)
  }

  onError (e: { stack: any }) {
    this.error(e.stack)
  }

  onStepBegin (test: TestCase, status: TestResult, step: TestStep) {
    this.debug(` - Step: ${step.title}`)
  }

  onStepEnd () {}

  onTestEnd (test: TestCase, result: TestResult) {
    this.info(
      'Test %s: %s%s',
      result.status,
      test.title,
      result.status === 'failed' && result.error ? `\n${result.error.stack}` : '',
    )
  }

  onEnd (result: FullResult) {}

  onStdOut (data: any) {
    this.silly(data)
  }

  onStdErr (data: any) {
    this.silly(data)
  }
}

export default Reporter
