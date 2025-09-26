# Linio

A simple, local, and elegant note system, centered around plain text files.

## Installation

This project uses the [Bun runtime](https://bun.sh) for compiling a full-stack web application into a single, standalone CLI executable. This is coupled with a `Bakefile`, a Bash-based replacement for `Makefile`. To build, run [`bake`](https://git.dupunkto.org/~meta/dotfiles/blob/master/bin/bake):

```shell
bake setup # Install dependencies
bake
```

This will built a standalone executable to `a.out`. The `Bakefile` provides a few nicities for local development too:

- `bake serve` serves a dev server with live reloading on port 9000.
- `bake install` installs the compiled binary to `XDG_BINARY_HOME` (defaults to `~/.local/bin` if unset).
- `bake reinstall` cleans, rebuilds and reinstalls the binary and provides optional integration with systemd.
- `bake clean` removes temporary files

## Usage

1. Start the server: `linio /path/to/your/data/directory`
2. Access the web interface at: [linio:9000](http://linio:9000)

| CLI Option                         | Description                                                                     |
| ---------------------------------- | ------------------------------------------------------------------------------- |
| `--lists=life,writing,work`        | Display order for task lists in ToDo page.                                      |
| `--backlogs=backlog,chores`        | Lists that are hidden by default.                                               |
| `--format=type`, `-h`, `-m`, `-b`  | Output format: `mixed` (`-b`, default), `headers` (`-h`), or `modifiers` (`-m`) |
| `--basic`                          | Disables client-side JavaScript libraries to reduce load on old hardware.       |

## Storage conventions

Notes are stored as top-level plain-text files. Each filename is a unique 5-character ID like `ABC12.txt` (called a HumID).

By default, every file in your data directory is treated as a note. You can link between notes using `#HUMID` references and organize content with `[[tags]]`.

```md
# My Project Ideas

Working on some interesting concepts for the new app. 

See the technical details in #XYZ89 and remember to check [[mobile]] compatibility.
```

### Note types

Notes can be converted to other types using either headers or modifiers to specify additional metadata. The following note types are supported:

- Tasks
- Wishes
- Bookmarks

Modifiers are lines starting with capital letters, tagging the note with metadata in a condense format. They can be added anywhere to the note. Alternatively, RFC5322-style headers are supported as well, resulting in cleaner, but more verbose, notes. By default Linio uses mixed format, which uses modifiers for note types, but headers for creation and modification dates.

#### Tasks

Any note containing the modifier `TODO` will be seen as a task. You can optionally include a deadline (`@ YYYY-MM-DD`) and list (`~list`) on the same line. The status of a task can be indicated using the `DONE` (to mark completion) and `NVM` (to shelve the task) modifiers, which take an optional date as well.

```txt
TODO[ @ YYYY[-MM[-DD]]][ ~LIST]
DONE[ @ YYYY[-MM[-DD]]] | NVM[ @ YYYY[-MM[-DD]]]
```

The following examples shows the same task in both modifier and header-based formats:

```md
TODO @ 2024-01-30 ~projects
DONE @ 2024-01-18

# Deploy new feature

Update the production server with the latest changes...
```

```md
Type: task
Task-List: projects
Task-Status: done
Task-Deadline: 2024-01-30
Task-Completed: 2024-01-18

# Deploy new feature

Update the production server with the latest changes...
```

#### Wishes

Wishes are similar to tasks, but lack deadlines and listings. Wishes support three modifiers: `WISH`, `BOUGHT`, `NVM`.

```txt
WISH
BOUGHT[ @ YYYY[-MM[-DD]]] | NVM[ @ YYYY[-MM[-DD]]]
```

The following examples shows the wish task in both modifier and header-based formats:

```md
WISH
BOUGHT @ 2024-02-28

# New shoes
```

```md
Type: wish
Wish-Status: bought
Wish-Bought: 2024-02-28

# New shoes
```

#### Bookmarks

Any note that begins with either `http://` or `https://` is automatically treated as a bookmark, making it easy to save and organize web links. At this point in time, no metadata is supported.

## Daemon management

It is possible to manage `linio` as a daemon using Systemd (Linux) or Launchd (Darwin) utilizing one of the following service files:

### `~/.config/systemd/user/linio.service`

```service
[Unit]
Description=Linio
After=network.target

[Service]
ExecStart=%h/.local/bin/linio %h/zettles
Restart=always
WorkingDirectory=%h/zettles
Environment=PATH=%h/.local/bin:/usr/local/bin:/usr/bin

[Install]
WantedBy=default.target
```

### `~/Library/LaunchAgents/org.dupunkto.linio.plist`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>org.dupunkto.linio</string>

    <key>ProgramArguments</key>
    <array>
        <string>/Users/axcelott/.local/bin/linio</string>
        <string>/Users/axcelott/zettles</string>
    </array>

    <key>KeepAlive</key>
    <true/>

    <key>RunAtLoad</key>
    <true/>

    <key>StandardOutPath</key>
    <string>/Users/axcelott/Library/Logs/linio.out.log</string>

    <key>StandardErrorPath</key>
    <string>/Users/axcelott/Library/Logs/linio.err.log</string>
</dict>
</plist>
```

(Note: MacOS plist files do not support variable or shell expansion for `~`, `$HOME`, or `%h`. So unfortunately, you'll have to hard-code full paths.)

To enable:

```shell
launchctl bootstrap gui/$(id -u) ~/library/LaunchAgents/org.dupunkto.linio.plist   
launchctl enable gui/$(id -u)/org.dupunkto.linio      
```

To disable:

```shell
launchctl bootout gui/$(id -u)/org.dupunkto.linio      
```
