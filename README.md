


Some sql for views:



create or replace view em_user_project_monthly_detail_view as
select
  project_id,
  year(session_start) as Year,
  month(session_start) as Month,
  username,
  sum(session_count) as Sessions,
  sum(duration) as Duration
from
  em_user_project_sessions
group by
  project_id,
  Year,
  Month,
  username
order by
  project_id asc,
  Year asc,
  Month asc,
  duration desc
;


create or replace view em_user_project_monthly_summary_view as
select
  project_id,
  year(session_start) as Year,
  month(session_start) as Month,
  count(distinct(username)) as Users,
  sum(session_count) as Sessions,
  sum(duration) as Duration
from
  em_user_project_sessions
group by
  project_id,
  Year,
  Month
order by
  project_id asc,
  Year asc,
  Month asc
;

