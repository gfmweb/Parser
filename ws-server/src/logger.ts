import winston from 'winston';

export type AppLogger = winston.Logger;

export function createLogger(): AppLogger {
  return winston.createLogger({
    level: process.env.LOG_LEVEL ?? 'info',
    defaultMeta: { service: 'ws-server' },
    format: winston.format.combine(
      winston.format.timestamp(),
      winston.format.errors({ stack: true }),
      winston.format.json(),
    ),
    transports: [new winston.transports.Console()],
  });
}

export function createSilentLogger(): AppLogger {
  return winston.createLogger({
    silent: true,
    transports: [new winston.transports.Console({ silent: true })],
  });
}
