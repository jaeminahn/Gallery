# 갤러리조명 Next.js

공개 사이트와 네이버 블로그 동기화 관리자 화면을 제공하는 Next.js 앱입니다.

## 설정

1. Supabase 프로젝트를 만들고 `supabase/schema.sql`을 SQL Editor에서 실행합니다.
2. Supabase Auth에서 관리자 사용자를 만들고 `admin_users`에 해당 UUID를 추가합니다. SQL 파일 끝에 예시가 있습니다.
3. 이 디렉터리의 `.env.local`에 Supabase 프로젝트 값을 입력합니다. 변수 목록은 `.env.example`에도 있으며, `.env.local`은 Git에 포함되지 않습니다.

   ```dotenv
   NEXT_PUBLIC_SITE_URL=
   NEXT_PUBLIC_SUPABASE_URL=
   NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY=
   SUPABASE_SECRET_KEY=
   APP_ENCRYPTION_KEY=
   ```

   `NEXT_PUBLIC_SITE_URL`에는 운영 도메인을 입력합니다. `APP_ENCRYPTION_KEY`는 관리자 화면에서 입력한 OpenAI API 키를 암호화하는 데 사용하므로, 로컬과 Vercel에 같은 값을 설정하고 이후에도 유지해야 합니다. OpenAI API 키는 관리자 화면의 **AI 재작성 설정**에서 입력하고 저장합니다.
4. `npm ci`와 `npm run dev`를 실행합니다. 공개 화면은 `/`, 관리자 화면은 `/admin/`입니다.
5. Vercel의 Root Directory를 `next-app`으로 지정하고 같은 환경 변수를 등록합니다.

동기화는 관리자 페이지가 열린 동안 실행됩니다. 네이버 목록을 한 페이지씩 확인하고 글을 한 건씩 가져와 AI 재작성 후 게시합니다. Cron은 사용하지 않습니다.

## 점검

`npm run typecheck`, `npm run lint`, `npm run build`를 실행합니다. Supabase 연결 후에는 관리자 로그인, 목록 갱신, 글 동기화, 취소와 실패 재시도를 확인하세요.
