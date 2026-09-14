import type { Logger } from 'winston';
import { WebSocket } from 'ws';
import type { ParseProgressPayload, WsMessage } from './types.js';

export class Broadcaster {
  private readonly subscriptions = new Map<string, Set<WebSocket>>();

  public constructor(private readonly logger: Logger) {}

  public subscribe(channel: string, ws: WebSocket): void {
    const subscribers = this.subscriptions.get(channel) ?? new Set<WebSocket>();
    subscribers.add(ws);
    this.subscriptions.set(channel, subscribers);
  }

  public unsubscribe(ws: WebSocket): void {
    for (const [channel, subscribers] of this.subscriptions) {
      subscribers.delete(ws);
      if (subscribers.size === 0) {
        this.subscriptions.delete(channel);
      }
    }
  }

  public broadcast(channel: string, payload: ParseProgressPayload): void {
    const subscribers = this.subscriptions.get(channel);
    const subscriberCount = subscribers?.size ?? 0;

    this.logger.info('broadcast', {
      channel,
      subscribers: subscriberCount,
      status: payload.status,
      organizationId: payload.organizationId,
    });

    if (subscribers === undefined || subscribers.size === 0) {
      return;
    }

    const message: WsMessage = {
      type: 'progress',
      channel,
      payload,
    };
    const serialized = JSON.stringify(message);

    for (const socket of subscribers) {
      if (socket.readyState !== WebSocket.OPEN) {
        continue;
      }

      try {
        socket.send(serialized);
      } catch (error) {
        const err = error instanceof Error ? error : new Error(String(error));
        this.logger.warn('failed to send broadcast', {
          channel,
          message: err.message,
        });
      }
    }
  }

  public getSubscriberCount(channel: string): number {
    return this.subscriptions.get(channel)?.size ?? 0;
  }

  public getTotalSubscriberCount(): number {
    const unique = new Set<WebSocket>();

    for (const subscribers of this.subscriptions.values()) {
      for (const socket of subscribers) {
        unique.add(socket);
      }
    }

    return unique.size;
  }
}
