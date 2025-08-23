# Linio

A simple, local, and elegant note system, centered around plain text files.

## Installation

Linio uses `Bakefile`, a Bash-based replacement for `Makefile`. You'll aneed [bake](https://git.dupunkto.org/~meta/dotfiles/blob/master/bin/bake) in your `PATH`.  
Linio uses the [Bun](https://bun.sh) runtime, so make sure you have that installed too.

1. Run `bake` in the repo root to build the binary (`a.out`)
2. Run `bake install` to install the binary to `~/.local/bin/linio` or `$XDG_BINARY_HOME/linio` (if you have `$XDG_BINARY_HOME` specified)
3. Add `~/.local/bin` or `$XDG_BINARY_HOME` to your `PATH`

## Usage

1. Start the server: `linio /path/to/your/data/directory`
2. Access the web interface at: [linio:9000](http://linio:9000)

### CLI options

| Option                | Description                                           |
| --------------------- | ----------------------------------------------------- |
| `--lists=list1,list2` | Display order for task lists in ToDo page             |
| `--format=type`       | Output format: headers, modifiers, or mixed (default) |
| `--basic`             | Use basic features only                               |
| `-h`                  | Use headers format (structured metadata)              |
| `-m`                  | Use modifiers format (keyword-based)                  |
| `-b`                  | Use mixed format (combines both approaches)           |

## File formats

Linio stores everything in plain text files with `.txt` extensions. Each file gets a unique 5-character ID (called a HUMID) like `ABC12.txt` for easy referencing. The web interface renders Markdown formatting, so you can use standard Markdown syntax in your files.

You have complete freedom in how you structure your content, just write however you want. If you include an H1 header (`#`), it becomes the file's title. Otherwise, Linio uses the beginning of the file. The only special requirement is using specific keywords when you want to create todos or wishes.

### Notes

By default, every file in your data directory is treated as a note. You can link between notes using `#HUMID` syntax and organize content with tags using `[[tag]]` brackets.

```md
# My Project Ideas

Working on some interesting concepts for the new app. 

See the technical details in #XYZ89 and remember to check [[mobile]] compatibility.
```

### Todos

Create todos using either keyword-based or structured header formats.

#### Keyword format

Start a file with `TODO` to create a task. You can optionally include a deadline (`@ YYYY-MM-DD`) and category (`~list-name`) on the same line. Mark completion or shelving by adding `DONE` or `NVM` on the second line, with optional dates.

```md
TODO @ 2024-01-30 ~projects
DONE @ 2024-01-18

# Deploy new feature

Update the production server with the latest changes...
```

**Syntax reference:**

```txt
TODO[ @ YYYY[-MM[-DD]]][ ~LIST]
DONE[ @ YYYY[-MM[-DD]]] | NVM[ @ YYYY[-MM[-DD]]]

[your content here]
```

#### Structured format

For more detailed metadata, use structured headers instead:

```txt
Type: task
Created: 2024-01-15
Modified: 2024-01-16
Task-Status: todo
Task-Deadline: 2024-01-30
Task-List: projects

# Deploy new feature

Update the production server with the latest changes...
```

### Wishes

Wishes work exactly like todos but use `BOUGHT` instead of `DONE` to mark completion and you can't use lists.

#### Keyword format

```md
WISH @ 2024-03-15
BOUGHT @ 2024-02-28

# New laptop

Just anything but a MacBook.
```

#### Structured format

```txt
Type: wish
Created: 2024-02-15
Wish-Status: wish
Wish-Deadline: 2024-03-15

# New laptop

Just anything but a MacBook.
```

**Syntax reference:**

```txt
WISH[ @ YYYY[-MM[-DD]]][ ~LIST]
BOUGHT[ @ YYYY[-MM[-DD]]] | NVM[ @ YYYY[-MM[-DD]]]

[your content here]
```

### Bookmarks

Any file that begins with a URL is automatically treated as a bookmark, making it easy to save and organize web links.
