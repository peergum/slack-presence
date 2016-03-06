slack-presence
==============

This project offers a set of simple commands that allows any company to
track their staff schedule:

#Recurrent schedule

A basic command allows people to inform where they will work each week day:
- from Home
- from Office
- Doesn't work that day

The commands `home`, `office` or `off` are simply followed by the respective days:
`office monday thursday`, `home wed`, `off fri`...

People's schedules are created the first time their use any command (see `people`)
and default to every weekday being an office day. So basically, if someone only works from
home on Wednesdays, then she would just type `home wed` and schedule will be set properly to
office on Monday, Tuesday, Thrusday and Friday, and home on Wednesday.

The argument to the `office`, `home`, and `off` commands are week days, and only need the 3 first characters of the day name, case being ignored.

#Specific events

A more complete command, `set`, allows everyone to set a specific event, be it
working from home on a Wednesday when the person usually works from office that day,
or a business travel for next week, or anything people want to let others know.

For instance, `set home mon` on a thrusday will set next week's Monday - exclusively - to
home, whereas the normal recurring schedule will be applied on other Mondays after that.

The `set` commands accepts week days as arguments, but also specific dates, informed as a month and a day (e.g. `March 3` or `mar3`), or any period that is expressed by a week day or a date followed
by `-` followed by another week day or date (e.g. `Thu - Mar 12`)

`set Vegas April 3 - April 17` will give you 2 weeks in Vegas, enjoy ;-) (*joke!*)

#Showing schedules

