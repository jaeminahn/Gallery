-- Run in the Supabase SQL editor before connecting the Next.js app.
create extension if not exists pgcrypto;

create table if not exists public.admin_users (
  user_id uuid primary key references auth.users(id) on delete cascade
);

create table if not exists public.posts (
  id uuid primary key default gen_random_uuid(),
  log_no text not null unique,
  slug text not null unique,
  title text not null,
  content_html text not null default '',
  excerpt text not null default '',
  first_image text,
  source_url text not null,
  status text not null default 'draft' check (status in ('draft', 'publish', 'pending', 'private')),
  published_at timestamptz not null default now(),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists posts_public_order on public.posts(status, published_at desc);

create table if not exists public.remote_posts (
  log_no text primary key,
  title text not null,
  posted_at text not null default '',
  thumbnail text not null default '',
  list_page integer not null default 1,
  list_position integer not null default 0,
  updated_at timestamptz not null default now()
);
create table if not exists public.sync_queue (
  id uuid primary key default gen_random_uuid(),
  log_no text not null unique,
  status text not null default 'pending' check (status in ('pending','processing','cancelling','failed')),
  attempts integer not null default 0,
  error text,
  started_at timestamptz,
  created_at timestamptz not null default now()
);
create index if not exists sync_queue_order on public.sync_queue(status, created_at);
create index if not exists remote_posts_order on public.remote_posts(list_page, list_position);

create table if not exists public.sync_state (
  id integer primary key default 1 check (id = 1),
  next_page integer not null default 1,
  complete boolean not null default false,
  paused_until timestamptz,
  last_request_at timestamptz,
  cancel_log_no text,
  api_key_ciphertext text,
  model text not null default 'gpt-4o-mini',
  post_status text not null default 'draft' check (post_status in ('draft','publish')),
  updated_at timestamptz not null default now()
);
insert into public.sync_state(id) values (1) on conflict (id) do nothing;

grant usage on schema public to service_role;
grant select, insert, update, delete on public.admin_users, public.posts, public.remote_posts, public.sync_queue, public.sync_state to service_role;

create or replace function public.claim_next_sync() returns public.sync_queue
language plpgsql security definer set search_path = public as $$
declare item public.sync_queue;
begin
  if not pg_try_advisory_xact_lock(741963) then return null; end if;
  update public.sync_queue set status = 'pending', started_at = null
  where status = 'processing' and started_at < now() - interval '10 minutes';
  if exists (select 1 from public.sync_queue where status in ('processing','cancelling')) then
    return null;
  end if;
  select * into item from public.sync_queue where status = 'pending'
  order by created_at for update skip locked limit 1;
  if item.id is not null then
    update public.sync_queue set status = 'processing', started_at = now()
    where id = item.id returning * into item;
  end if;
  return item;
end;
$$;
revoke all on function public.claim_next_sync() from public, anon, authenticated;
grant execute on function public.claim_next_sync() to service_role;

alter table public.admin_users enable row level security;
alter table public.posts enable row level security;
alter table public.remote_posts enable row level security;
alter table public.sync_queue enable row level security;
alter table public.sync_state enable row level security;
-- Server routes use the secret key (which runs as service_role) only after checking Supabase Auth + admin_users.
-- Public pages are rendered on the server and never receive this key.

-- After creating your first user in Supabase Auth, grant access explicitly:
-- insert into public.admin_users(user_id) values ('YOUR_AUTH_USER_UUID');
