import { z } from 'zod';

export const parseProgressPayloadSchema = z.object({
  organizationId: z.number().int().positive(),
  parseJobId: z.number().int().positive(),
  total: z.number().int().nonnegative(),
  parsed: z.number().int().nonnegative(),
  status: z.enum(['parsing', 'done', 'failed']),
  error: z.string().nullable(),
});

export const parseChannelSchema = z
  .string()
  .regex(/^parse\.\d+$/, 'Channel must match parse.{organizationId}');

const subscribeMessageSchema = z.object({
  type: z.literal('subscribe'),
  channel: parseChannelSchema,
});

const progressMessageSchema = z.object({
  type: z.literal('progress'),
  channel: parseChannelSchema,
  payload: parseProgressPayloadSchema,
});

const errorMessageSchema = z.object({
  type: z.literal('error'),
  channel: z.string(),
  message: z.string(),
});

export const wsMessageSchema = z.discriminatedUnion('type', [
  subscribeMessageSchema,
  progressMessageSchema,
  errorMessageSchema,
]);

export const clientSubscribeMessageSchema = subscribeMessageSchema;
