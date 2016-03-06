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

To list people's schedules, just type `people`. You'll get current week's schedule as shown
in the example below:

```
+============+===========+===========+===========+===========+===========+
| Person     | Monday 07 | Tuesda 08 | Wednes 09 | Thursd 10 | Friday 11 |
+============+===========+===========+===========+===========+===========+
|     andrew | ...... OFFICE ....... |   HOME    |  OFFICE   |   HOME    |
|    andrewk | ........................ OFFICE ......................... |
|     angela | ........................ OFFICE ......................... |
|    ciprian |  OFFICE   | ....... HOME ........ |  OFFICE   |   HOME    |
|     cydney | ......................... HOME .......................... |
|      daveb | ........................ OFFICE ......................... |
|      davec | ............ OFFICE ............. | ....... HOME ........ |
|       hawk | ......................... HOME .......................... |
|      jason |   HOME    |  OFFICE   |   HOME    |  OFFICE   |   HOME    |
|       judy | ......................... HOME .......................... |
|      leeia |  OFFICE   |   HOME    | ...... OFFICE ....... |   HOME    |
|    melanie |   HOME    | ............ OFFICE ............. |   HOME    |
|    michael | ............. HOME .............. |  OFFICE   |   HOME    |
|       phil | ...... OFFICE ....... |   HOME    |  OFFICE   |   HOME    |
|       rick | .................. OFFICE ................... |   HOME    |
|      sapna |   HOME    |  OFFICE   |   HOME    |  OFFICE   |   HOME    |
|       sara | ........................ OFFICE ......................... |
|      thais | ................... HOME .................... |   HOME*   |
|     thomas | ......................... HOME .......................... |
|      tolga | ......................... HOME .......................... |
|     travis | ........................ OFFICE ......................... |
|     victor | ........................ OFFICE ......................... |
|     yannie | ...... OFFICE ....... |   HOME    | ...... OFFICE ....... |
|       yuzo |   HOME    | ............ OFFICE ............. |   HOME    |
+============+===========+===========+===========+===========+===========+
| OFFICE --> |  54% (13) |  62% (15) |  45% (11) |  70% (17) |  29% ( 7) |
+============+===========+===========+===========+===========+===========+
```

`people` also show the number of people physically present at the office every day,
which is useful if you need to order meals for lunch & learns, etc...

Also note specific schedules (not recurring) are shown with a `*`.

If you're on a phone, use `compact` instead, which will give you a narrower view
more adapted to phones, without the stats line, and possibly without the `*` for
specific schedules (depending on size available):

```
+============+===+===+===+===+===+
| Person     | M | T | W | T | F |
+============+===+===+===+===+===+
|     andrew | OFFIC | H | O | H |
|    andrewk | .... OFFICE ..... |
|     angela | .... OFFICE ..... |
|    ciprian | O | HOME  | O | H |
|     cydney | ..... HOME ...... |
|      daveb | .... OFFICE ..... |
|      davec |  OFFICE . | HOME  |
|       hawk | ..... HOME ...... |
|      jason | H | O | H | O | H |
|       judy | ..... HOME ...... |
|      leeia | O | H | OFFIC | H |
|    melanie | H |  OFFICE . | H |
|    michael | . HOME .. | O | H |
|       phil | OFFIC | H | O | H |
|       rick | .. OFFICE ... | H |
|      sapna | H | O | H | O | H |
|       sara | .... OFFICE ..... |
|      thais | ... HOME .... | H |
|     thomas | ..... HOME ...... |
|      tolga | ..... HOME ...... |
|     travis | .... OFFICE ..... |
|     victor | .... OFFICE ..... |
|     yannie | OFFIC | H | OFFIC |
|       yuzo | H |  OFFICE . | H |
+============+===+===+===+===+===+
```

If you need to see more than one week, `2weeks` will show you this week and the next one,
and `month` will show you this week and the next three ones (sliding month).

##Available soon:

adding `teams` to the command will show you the same results but with people organized by
team:
```
+============+===========+===========+===========+===========+===========+
| Person     | Monday 07 | Tuesda 08 | Wednes 09 | Thursd 10 | Friday 11 |
+============+===========+===========+===========+===========+===========+
|      davec | ............ OFFICE ............. | ....... HOME ........ |
|       yuzo |   HOME    | ............ OFFICE ............. |   HOME    |
+============+===========+===========+===========+===========+===========+
|     angela | ........................ OFFICE ......................... |
|      daveb | ........................ OFFICE ......................... |
|      jason |   HOME    |  OFFICE   |   HOME    |  OFFICE   |   HOME    |
|      leeia |  OFFICE   |   HOME    | ...... OFFICE ....... |   HOME    |
|    melanie |   HOME    | ............ OFFICE ............. |   HOME    |
|       sara | ........................ OFFICE ......................... |
|      thais | ................... HOME .................... |   HOME*   |
|      tolga | ......................... HOME .......................... |
+============+===========+===========+===========+===========+===========+
|     cydney | ......................... HOME .......................... |
|       judy | ......................... HOME .......................... |
|       rick | .................. OFFICE ................... |   HOME    |
|      sapna |   HOME    |  OFFICE   |   HOME    |  OFFICE   |   HOME    |
|     yannie | ...... OFFICE ....... |   HOME    | ...... OFFICE ....... |
+============+===========+===========+===========+===========+===========+
|       hawk | ......................... HOME .......................... |
|    michael | ............. HOME .............. |  OFFICE   |   HOME    |
|     travis | ........................ OFFICE ......................... |
+============+===========+===========+===========+===========+===========+
|     andrew | ...... OFFICE ....... |   HOME    |  OFFICE   |   HOME    |
|    andrewk | ........................ OFFICE ......................... |
|    ciprian |  OFFICE   | ....... HOME ........ |  OFFICE   |   HOME    |
|       phil | ...... OFFICE ....... |   HOME    |  OFFICE   |   HOME    |
|     thomas | ......................... HOME .......................... |
|     victor | ........................ OFFICE ......................... |
+============+===========+===========+===========+===========+===========+
| OFFICE --> |  54% (13) |  62% (15) |  45% (11) |  70% (17) |  29% ( 7) |
+============+===========+===========+===========+===========+===========+
```

#Calendar

`calendar` shows you a simple calendar for previous month, current month and next month.

#Holidays

Legal holidays are shown depending on user's country, with the holiday name or
a `*` in the compact view.

_(coming soon)_

#Defining teams

People can be assigned to teams.

_(coming soon)_



